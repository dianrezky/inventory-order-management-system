<?php

namespace App\Core\Mail;

// Minimal native SMTP client (no mail library): implicit TLS (ssl, port 465) or STARTTLS (tls, port 587), AUTH LOGIN.
// Server certificates are always verified. Credentials and message bodies are never written to the error log.
class SmtpMailer implements MailerInterface
{
    private $host;
    private $port;
    private $encryption;
    private $username;
    private $password;
    private $fromAddress;
    private $fromName;
    private $timeout;

    public function __construct($host, $port, $encryption, $username, $password, $fromAddress, $fromName, $timeout = 15)
    {
        $this->host = (string) $host;
        $this->port = (int) $port;
        $this->encryption = strtolower((string) $encryption);
        $this->username = (string) $username;
        $this->password = (string) $password;
        $this->fromAddress = (string) $fromAddress;
        $this->fromName = (string) $fromName;
        $this->timeout = (int) $timeout;
    }

    public function send($toAddress, $toName, $subject, $textBody)
    {
        if ($this->host === '') {
            throw new MailException('MAIL_HOST is not configured.');
        }

        $from = MessageBuilder::assertAddress($this->fromAddress);
        $to = MessageBuilder::assertAddress($toAddress);
        $message = MessageBuilder::build($from, $this->fromName, $to, $toName, $subject, $textBody, $this->domainOf($from));

        $target = ($this->encryption === 'ssl' ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;
        $socket = @stream_socket_client($target, $errno, $errstr, $this->timeout, STREAM_CLIENT_CONNECT);
        if ($socket === false) {
            throw new MailException("SMTP connect failed ({$errno}).");
        }
        stream_set_timeout($socket, $this->timeout);

        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . $this->ehloName(), [250]);

            if ($this->encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT) !== true) {
                    throw new MailException('SMTP STARTTLS negotiation failed.');
                }
                $this->command($socket, 'EHLO ' . $this->ehloName(), [250]);
            }

            if ($this->username !== '') {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode($this->username), [334], true);
                $this->command($socket, base64_encode($this->password), [235], true);
            }

            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);

            // Dot-stuffing: a line starting with "." would otherwise end the message early.
            $stuffed = preg_replace('/^\./m', '..', $message);
            $this->write($socket, $stuffed . "\r\n.");
            $this->expect($socket, [250], 'end of DATA');

            $this->command($socket, 'QUIT', [221, 250]);
        } finally {
            @fclose($socket);
        }
    }

    private function command($socket, $line, array $okCodes, $secret = false)
    {
        $this->write($socket, $line);
        $this->expect($socket, $okCodes, $secret ? '[credentials]' : $line);
    }

    private function write($socket, $line)
    {
        if (@fwrite($socket, $line . "\r\n") === false) {
            throw new MailException('SMTP write failed.');
        }
    }

    // Reads a (possibly multi-line) reply and checks its code.
    private function expect($socket, array $okCodes, $context = 'greeting')
    {
        $code = null;
        $text = '';
        while (($line = fgets($socket, 1024)) !== false) {
            $code = (int) substr($line, 0, 3);
            $text = trim(substr($line, 4));
            if (isset($line[3]) && $line[3] === '-') {
                continue;
            }
            break;
        }

        if ($code === null || !in_array($code, $okCodes, true)) {
            throw new MailException('SMTP error after "' . $context . '": ' . ($code ?? 'no reply') . ' ' . $text);
        }
    }

    private function ehloName()
    {
        $name = gethostname();

        return is_string($name) && preg_match('/^[A-Za-z0-9.-]+$/', $name) === 1 ? $name : 'localhost';
    }

    private function domainOf($address)
    {
        $at = strrpos((string) $address, '@');

        return $at === false ? 'localhost' : substr((string) $address, $at + 1);
    }
}
