<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\FakeClock;
use App\Core\Result;
use Tests\Support\InMemorySessionManager;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\Fake\UserFakeRepository;
use App\Service\AuthService;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class AuthServiceTest extends TestCase
{
    private function makeUser(int $id, string $email, string $plainPassword, string $role, bool $active = true): User
    {
        return new User(
            id: $id,
            name: 'Test User ' . $id,
            email: $email,
            passwordHash: password_hash($plainPassword, PASSWORD_BCRYPT),
            role: $role,
            isActive: $active,
        );
    }

    private function sessionManager()
    {
        return new InMemorySessionManager();
    }

    public function testLoginSucceedsWithValidCredentials(): void
    {
        $repo = new UserFakeRepository([
            $this->makeUser(1, 'sales1@example.com', 'sales123', Role::Sales->value),
        ]);
        $auth = new AuthService($repo, $this->sessionManager());

        $result = $auth->login('sales1@example.com', 'sales123');

        self::assertSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame('sales1@example.com', $result->data->email);
        self::assertSame(Role::Sales->value, $result->data->role);
    }

    public function testLoginFailsWithWrongPassword(): void
    {
        $repo = new UserFakeRepository([
            $this->makeUser(1, 'sales1@example.com', 'sales123', Role::Sales->value),
        ]);
        $auth = new AuthService($repo, $this->sessionManager());

        $result = $auth->login('sales1@example.com', 'wrong-password');

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame('The email address or password you entered is incorrect.', $result->info);
    }

    public function testLoginFailsForUnknownEmail(): void
    {
        $repo = new UserFakeRepository([]);
        $auth = new AuthService($repo, $this->sessionManager());

        $result = $auth->login('nobody@example.com', 'anything');

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame('The email address or password you entered is incorrect.', $result->info);
    }

    public function testLoginFailsForInactiveUser(): void
    {
        $repo = new UserFakeRepository([
            $this->makeUser(1, 'inactive@example.com', 'secret123', Role::Sales->value, active: false),
        ]);
        $auth = new AuthService($repo, $this->sessionManager());

        $result = $auth->login('inactive@example.com', 'secret123');

        self::assertNotSame(Result::CODE_SUCCESS, $result->code);
        self::assertSame('The email address or password you entered is incorrect.', $result->info);
    }

    public function testCurrentUserReturnsNullWhenNotLoggedIn(): void
    {
        $repo = new UserFakeRepository([]);
        $session = $this->sessionManager();
        $session->remove('user_id');
        $auth = new AuthService($repo, $session);

        $this->assertNull($auth->currentUser());
    }

    public function testLogoutClearsSession()
    {
        $repo = new UserFakeRepository([
            $this->makeUser(1, 'admin@example.com', 'admin123', Role::Admin->value),
        ]);
        $session = $this->sessionManager();
        $auth = new AuthService($repo, $session);

        $loginResult = $auth->login('admin@example.com', 'admin123');
        $this->assertSame(0, $loginResult->code);
        $this->assertNotNull($auth->currentUser());

        $auth->logout();

        $this->assertNull($auth->currentUser());
    }

    public function testCurrentUserExpiresAfterIdleTimeoutUsingFakeClock(): void
    {
        $repo = new UserFakeRepository([
            $this->makeUser(1, 'sales1@example.com', 'sales123', Role::Sales->value),
        ]);
        $session = $this->sessionManager();
        $clock = new FakeClock(new DateTimeImmutable('2026-01-01 00:00:00'));
        $auth = new AuthService($repo, $session, null, $clock);

        $loginResult = $auth->login('sales1@example.com', 'sales123');
        self::assertSame(Result::CODE_SUCCESS, $loginResult->code);

        // Still well within the session's 3600s lifetime: idle timeout does not fire.
        $clock->set(new DateTimeImmutable('2026-01-01 00:30:00'));
        self::assertNotNull($auth->currentUser());

        // Past the lifetime since the last recorded activity: idle timeout fires deterministically, no sleep() needed.
        $clock->set(new DateTimeImmutable('2026-01-01 02:00:00'));
        self::assertNull($auth->currentUser());
    }
}
