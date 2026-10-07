<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Mail\MailException;
use App\Core\Mail\MessageBuilder;
use App\Core\Mail\SmtpMailer;
use PHPUnit\Framework\TestCase;

final class SmtpMailerTest extends TestCase
{
    private function startServer(bool $rejectRcpt = false): array
    {
        $dir = sys_get_temp_dir() . '/smtp-test-' . bin2hex(random_bytes(4));
        mkdir($dir);
        $cmd = [PHP_BINARY, __DIR__ . '/../Support/fake-smtp-server.php', $dir . '/port', $dir . '/out'];
        if ($rejectRcpt) {
            $cmd[] = 'reject-rcpt';
        }
        $proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        for ($i = 0; $i < 100 && !is_file($dir . '/port'); $i++) {
            usleep(50000);
        }
        self::assertTrue(is_file($dir . '/port'), 'fake SMTP server did not start');
        usleep(50000);

        return [$proc, $dir, (int) file_get_contents($dir . '/port')];
    }

    private function finish($proc, string $dir): string
    {
        proc_close($proc);

        return (string) @file_get_contents($dir . '/out');
    }

    public function testSendsDialogueWithAuthAndDotStuffedBody(): void
    {
        [$proc, $dir, $port] = $this->startServer();
        $mailer = new SmtpMailer('127.0.0.1', $port, 'none', 'smtp-user', 'secret-pw', 'noreply@example.com', 'IOMS', 5);

        $mailer->send('ian@example.com', 'Ian R', 'Reset your password', "Line one\n.starts with dot\nhttps://x/reset-password#token=abc\n");
        $transcript = $this->finish($proc, $dir);

        self::assertStringContainsString('C: MAIL FROM:<noreply@example.com>', $transcript);
        self::assertStringContainsString('C: RCPT TO:<ian@example.com>', $transcript);
        self::assertStringContainsString('C: AUTH LOGIN', $transcript);
        self::assertStringContainsString('C: ' . base64_encode('smtp-user'), $transcript);
        self::assertStringContainsString('DATA: Subject: Reset your password', $transcript);
        self::assertStringContainsString('DATA: Content-Transfer-Encoding: base64', $transcript);
        preg_match_all('/^DATA: ([A-Za-z0-9+\/=]+)$/m', $transcript, $m);
        self::assertNotEmpty($m[1]);
        // The body is base64 so no body line can ever be a lone "." (and dot-stuffing covers the rest).
        self::assertStringNotContainsString("\nDATA: .\n", "\n" . $transcript);
    }

    public function testRejectedRecipientThrows(): void
    {
        [$proc, $dir, $port] = $this->startServer(true);
        $mailer = new SmtpMailer('127.0.0.1', $port, 'none', '', '', 'noreply@example.com', 'IOMS', 5);

        try {
            $mailer->send('ian@example.com', 'Ian', 'Hi', 'Body');
            self::fail('expected MailException');
        } catch (MailException $e) {
            self::assertStringContainsString('550', $e->getMessage());
        } finally {
            $this->finish($proc, $dir);
        }
    }

    public function testConnectionFailureThrows(): void
    {
        $mailer = new SmtpMailer('127.0.0.1', 1, 'none', '', '', 'noreply@example.com', 'IOMS', 2);

        $this->expectException(MailException::class);
        $mailer->send('ian@example.com', 'Ian', 'Hi', 'Body');
    }

    public function testHeaderInjectionIsRejected(): void
    {
        $this->expectException(MailException::class);
        MessageBuilder::build('a@example.com', 'A', 'b@example.com', 'B', "Hi\r\nBcc: evil@example.com", 'Body');
    }

    public function testInvalidRecipientIsRejected(): void
    {
        $this->expectException(MailException::class);
        MessageBuilder::build('a@example.com', 'A', "b@example.com\r\nRCPT TO:<evil@example.com>", 'B', 'Hi', 'Body');
    }

    public function testNonAsciiNameAndSubjectAreEncoded(): void
    {
        $message = MessageBuilder::build('a@example.com', 'Siti Nurhaliza', 'b@example.com', 'Budi Šantoso', 'Atur ulang kata sandi — IOMS', 'Halo');

        self::assertStringContainsString('Subject: =?UTF-8?B?', $message);
        self::assertStringContainsString('To: =?UTF-8?B?', $message);
        self::assertStringContainsString('From: Siti Nurhaliza <a@example.com>', $message);
    }
}
