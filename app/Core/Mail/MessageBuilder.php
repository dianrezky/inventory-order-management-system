<?php

namespace App\Core\Mail;

// Builds an RFC 5322 text/plain message. Header values are rejected when they contain CR/LF (header injection).
class MessageBuilder
{
    public static function build($fromAddress, $fromName, $toAddress, $toName, $subject, $textBody, $messageIdDomain = 'localhost')
    {
        $headers = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . self::mailbox($fromAddress, $fromName),
            'To: ' . self::mailbox($toAddress, $toName),
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(16)) . '@' . self::assertSingleLine($messageIdDomain) . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        $body = chunk_split(base64_encode((string) $textBody), 76, "\r\n");

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }

    public static function assertAddress($address)
    {
        $address = self::assertSingleLine($address);
        if (filter_var($address, FILTER_VALIDATE_EMAIL) === false) {
            throw new MailException('Invalid e-mail address.');
        }

        return $address;
    }

    private static function mailbox($address, $name)
    {
        $address = self::assertAddress($address);
        $name = trim((string) $name);
        if ($name === '') {
            return '<' . $address . '>';
        }

        return self::encodeHeader($name) . ' <' . $address . '>';
    }

    private static function encodeHeader($value)
    {
        $value = self::assertSingleLine($value);
        if (preg_match('/^[\x20-\x7e]*$/', $value) === 1) {
            return $value;
        }

        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private static function assertSingleLine($value)
    {
        $value = (string) $value;
        if (preg_match('/[\r\n\0]/', $value) === 1) {
            throw new MailException('Illegal line break in mail header.');
        }

        return $value;
    }
}
