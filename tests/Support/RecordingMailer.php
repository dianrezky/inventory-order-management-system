<?php

namespace Tests\Support;

use App\Core\Mail\MailerInterface;
use App\Core\Mail\MailException;

// Mailer test double: records messages; can be told to fail the next N sends.
class RecordingMailer implements MailerInterface
{
    public $sent = [];
    public $failNext = 0;

    public function send($toAddress, $toName, $subject, $textBody)
    {
        if ($this->failNext > 0) {
            $this->failNext--;
            throw new MailException('SMTP down');
        }
        $this->sent[] = ['to' => $toAddress, 'name' => $toName, 'subject' => $subject, 'body' => $textBody];
    }

    // Extracts the 64-hex token from the most recent message's link.
    public function lastToken()
    {
        $last = end($this->sent);
        preg_match('/#token=([0-9a-f]{64})/', $last['body'], $m);

        return $m[1] ?? null;
    }
}
