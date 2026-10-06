<?php

namespace App\Core\Mail;

// Outbound mail boundary: services depend on this, never on a transport (SMTP, file, test double).
interface MailerInterface
{
    // Sends a plain-text message. Throws MailException when delivery was not accepted.
    public function send($toAddress, $toName, $subject, $textBody);
}
