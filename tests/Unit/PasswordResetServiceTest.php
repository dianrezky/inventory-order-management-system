<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\FakeClock;
use App\Core\FakeTransactionManager;
use App\Core\Result;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\Fake\PasswordResetFakeRepository;
use App\Repository\Fake\UserFakeRepository;
use App\Service\PasswordResetService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\RecordingMailer;

final class PasswordResetServiceTest extends TestCase
{
    private UserFakeRepository $users;
    private PasswordResetFakeRepository $resets;
    private RecordingMailer $mailer;
    private FakeClock $clock;
    private PasswordResetService $service;

    protected function setUp(): void
    {
        $this->users = new UserFakeRepository([
            new User(1, 'Sales One', 'sales1@example.com', password_hash('old-pass', PASSWORD_BCRYPT), Role::Sales, true),
            new User(2, 'Gone', 'gone@example.com', password_hash('x', PASSWORD_BCRYPT), Role::Sales, false),
        ]);
        $this->resets = new PasswordResetFakeRepository();
        $this->mailer = new RecordingMailer();
        $this->clock = new FakeClock(new DateTimeImmutable('2026-10-06 10:00:00'));
        $this->service = new PasswordResetService(
            $this->users,
            $this->resets,
            $this->mailer,
            new FakeTransactionManager(),
            $this->clock,
            null,
            'https://ioms.example.com/'
        );
    }

    private function advance(string $modify): void
    {
        $this->clock->set($this->clock->now()->modify($modify));
    }

    public function testRequestStoresPendingRowAndSendsNoEmailInline(): void
    {
        $result = $this->service->requestReset('Sales1@Example.com', '1.2.3.4');

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertCount(1, $this->resets->requests);
        self::assertSame(0, $this->resets->requests[1]->status);
        self::assertNull($this->resets->requests[1]->tokenHash);
        self::assertSame([], $this->mailer->sent);
    }

    public function testResponseIsIdenticalForUnknownAndInactiveEmails(): void
    {
        $known = $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $unknown = $this->service->requestReset('nobody@example.com', '1.2.3.5');
        $inactive = $this->service->requestReset('gone@example.com', '1.2.3.6');
        $invalid = $this->service->requestReset('not-an-email', '1.2.3.7');

        foreach ([$unknown, $inactive, $invalid] as $other) {
            self::assertSame($known->code, $other->code);
            self::assertSame($known->info, $other->info);
        }
        self::assertCount(1, $this->resets->requests, 'only the active user gets a row');
    }

    public function testDuplicatePendingRequestIsNotCreatedTwice(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->service->requestReset('sales1@example.com', '1.2.3.4');

        self::assertCount(1, $this->resets->requests);
    }

    public function testRateLimitPerEmailStopsNewRowsButKeepsGenericResponse(): void
    {
        for ($i = 0; $i < PasswordResetService::RATE_LIMIT_PER_EMAIL; $i++) {
            $this->service->requestReset('sales1@example.com', '9.9.9.' . $i);
            $this->service->sendPending();
            $this->resets->requests[array_key_last($this->resets->requests)]->usedAt = 'x'; // not pending any more
        }
        $before = count($this->resets->requests);

        $result = $this->service->requestReset('sales1@example.com', '9.9.9.99');

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame(PasswordResetService::MESSAGE_GENERIC_REQUEST, $result->info);
        self::assertCount($before, $this->resets->requests);
    }

    public function testRateLimitPerIp(): void
    {
        for ($i = 0; $i < PasswordResetService::RATE_LIMIT_PER_IP; $i++) {
            $this->service->requestReset("user{$i}@example.com", '5.5.5.5');
        }

        $this->service->requestReset('sales1@example.com', '5.5.5.5');

        self::assertCount(0, $this->resets->requests);
    }

    public function testCronSendsOnlyStatusZeroRowsThenMarksSentAndNeverResends(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');

        $first = $this->service->sendPending();
        self::assertSame(1, $first->data['sent']);
        self::assertCount(1, $this->mailer->sent);
        self::assertSame('sales1@example.com', $this->mailer->sent[0]['to']);
        self::assertSame(1, $this->resets->requests[1]->status);
        self::assertStringContainsString('https://ioms.example.com/reset-password#token=', $this->mailer->sent[0]['body']);

        $second = $this->service->sendPending();
        self::assertSame(0, $second->data['sent']);
        self::assertCount(1, $this->mailer->sent, 'status 1 rows are never picked again');
    }

    public function testOnlyStoredHashNotRawTokenIsPersisted(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->service->sendPending();

        $token = $this->mailer->lastToken();
        self::assertNotNull($token);
        self::assertSame(hash('sha256', $token), $this->resets->requests[1]->tokenHash);
        self::assertNotSame($token, $this->resets->requests[1]->tokenHash);
    }

    public function testAlreadyClaimedRowIsSkippedSoNoDoubleSend(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        // Another worker claimed the row between our SELECT and our UPDATE.
        $this->resets->claim(1, hash('sha256', 'other-worker'), '2026-10-06 10:00:00', '2026-10-06 10:05:00', 5);

        $result = $this->service->sendPending();

        self::assertSame(0, $result->data['sent']);
        self::assertSame([], $this->mailer->sent);
    }

    public function testFailedSendIsRetriedAfterBackoffThenSucceeds(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->mailer->failNext = 1;

        $first = $this->service->sendPending();
        self::assertSame(1, $first->data['failed']);
        self::assertSame(0, $this->resets->requests[1]->status);
        self::assertSame('SMTP down', $this->resets->requests[1]->lastError);
        self::assertNull($this->resets->requests[1]->tokenHash, 'token of a failed attempt is discarded');

        $tooSoon = $this->service->sendPending();
        self::assertSame(0, $tooSoon->data['sent'], 'back-off not elapsed');

        $this->advance('+' . PasswordResetService::RETRY_BACKOFF_MINUTES . ' minutes');
        $retry = $this->service->sendPending();
        self::assertSame(1, $retry->data['sent']);
        self::assertSame(1, $this->resets->requests[1]->status);
    }

    public function testGivesUpAfterMaxAttempts(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->mailer->failNext = 100;

        for ($i = 0; $i < PasswordResetService::MAX_SEND_ATTEMPTS; $i++) {
            $this->service->sendPending();
            $this->advance('+30 minutes');
            $this->resets->requests[1]->createdAt = $this->clock->now()->format('Y-m-d H:i:s'); // keep it from going stale
        }

        self::assertSame(2, $this->resets->requests[1]->status);
        self::assertSame([], $this->mailer->sent);
    }

    public function testStalePendingRequestIsAbandonedNotSent(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->advance('+' . (PasswordResetService::PENDING_TTL_MINUTES + 1) . ' minutes');

        $result = $this->service->sendPending();

        self::assertSame(1, $result->data['expired']);
        self::assertSame(2, $this->resets->requests[1]->status);
        self::assertSame([], $this->mailer->sent);
    }

    public function testUserDeactivatedBeforeSendIsSkipped(): void
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->users->setActive(1, false);

        $result = $this->service->sendPending();

        self::assertSame(1, $result->data['skipped']);
        self::assertSame([], $this->mailer->sent);
        self::assertSame(2, $this->resets->requests[1]->status);
    }

    public function testMissingAppUrlSendsNothing(): void
    {
        $service = new PasswordResetService($this->users, $this->resets, $this->mailer, new FakeTransactionManager(), $this->clock, null, '');
        $service->requestReset('sales1@example.com', '1.2.3.4');

        $result = $service->sendPending();

        self::assertSame(Result::CODE_INTERNAL, $result->code);
        self::assertSame([], $this->mailer->sent);
        self::assertSame(0, $this->resets->requests[1]->attempts);
    }

    private function issueToken(): string
    {
        $this->service->requestReset('sales1@example.com', '1.2.3.4');
        $this->service->sendPending();

        return (string) $this->mailer->lastToken();
    }

    public function testResetChangesPasswordWithBcryptAndTokenIsSingleUse(): void
    {
        $token = $this->issueToken();

        $result = $this->service->resetPassword($token, 'brand-new-pass', 'brand-new-pass');

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        $hash = $this->users->findById(1)->data->passwordHash;
        self::assertTrue(password_verify('brand-new-pass', $hash));
        self::assertStringStartsWith('$2y$', $hash);

        $again = $this->service->resetPassword($token, 'another-pass-1', 'another-pass-1');
        self::assertSame(Result::CODE_VALIDATION, $again->code);
        self::assertSame(PasswordResetService::MESSAGE_INVALID_TOKEN, $again->info);
        self::assertTrue(password_verify('brand-new-pass', $this->users->findById(1)->data->passwordHash));
    }

    public function testExpiredTokenIsRejected(): void
    {
        $token = $this->issueToken();
        $this->advance('+' . (PasswordResetService::TOKEN_TTL_MINUTES + 1) . ' minutes');

        $result = $this->service->resetPassword($token, 'brand-new-pass', 'brand-new-pass');

        self::assertSame(PasswordResetService::MESSAGE_INVALID_TOKEN, $result->info);
        self::assertTrue(password_verify('old-pass', $this->users->findById(1)->data->passwordHash));
    }

    public function testUnknownOrMalformedTokenIsRejected(): void
    {
        $this->issueToken();

        foreach (['', 'short', str_repeat('a', 64), str_repeat('g', 64)] as $bad) {
            $result = $this->service->resetPassword($bad, 'brand-new-pass', 'brand-new-pass');
            self::assertSame(Result::CODE_VALIDATION, $result->code);
            self::assertSame(PasswordResetService::MESSAGE_INVALID_TOKEN, $result->info);
        }
    }

    public function testBadPasswordDoesNotConsumeTheToken(): void
    {
        $token = $this->issueToken();

        self::assertSame(PasswordResetService::MESSAGE_PASSWORD_SHORT, $this->service->resetPassword($token, 'abc', 'abc')->info);
        self::assertSame(PasswordResetService::MESSAGE_PASSWORD_MISMATCH, $this->service->resetPassword($token, 'brand-new-pass', 'different-pass')->info);
        self::assertSame(Result::CODE_SUCCESS, $this->service->resetPassword($token, 'brand-new-pass', 'brand-new-pass')->code);
    }

    public function testSuccessfulResetBurnsOtherOpenRequests(): void
    {
        $oldToken = $this->issueToken();
        $this->advance('+1 minutes');
        $newToken = $this->issueToken();
        self::assertNotSame($oldToken, $newToken);

        self::assertSame(Result::CODE_SUCCESS, $this->service->resetPassword($newToken, 'brand-new-pass', 'brand-new-pass')->code);
        self::assertSame(Result::CODE_VALIDATION, $this->service->resetPassword($oldToken, 'third-pass-1', 'third-pass-1')->code);
    }

    public function testDeactivatedUserCannotResetWithAValidToken(): void
    {
        $token = $this->issueToken();
        $this->users->setActive(1, false);

        $result = $this->service->resetPassword($token, 'brand-new-pass', 'brand-new-pass');

        self::assertSame(PasswordResetService::MESSAGE_INVALID_TOKEN, $result->info);
        self::assertTrue(password_verify('old-pass', $this->users->findById(1)->data->passwordHash));
    }
}
