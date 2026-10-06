<?php

namespace App\Core\Mail;

// Local-development transport (MAIL_TRANSPORT=file): writes each message as an .eml file instead of sending it.
class FileMailer implements MailerInterface
{
    private $directory;
    private $fromAddress;
    private $fromName;

    public function __construct($directory, $fromAddress, $fromName)
    {
        $this->directory = $directory;
        $this->fromAddress = $fromAddress;
        $this->fromName = $fromName;
    }

    public function send($toAddress, $toName, $subject, $textBody)
    {
        $message = MessageBuilder::build($this->fromAddress, $this->fromName, $toAddress, $toName, $subject, $textBody);

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new MailException('Mail directory is not writable.');
        }

        $path = rtrim($this->directory, '/') . '/' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.eml';
        if (@file_put_contents($path, $message) === false) {
            throw new MailException('Could not write the mail file.');
        }
    }
}
