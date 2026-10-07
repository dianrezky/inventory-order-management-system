<?php
// Throwaway one-connection SMTP server for SmtpMailerTest. Usage: php fake-smtp-server.php <port-file> <out-file> [reject-rcpt]
// Plays a plain (no TLS) SMTP dialogue with AUTH LOGIN and writes the received transcript + DATA to <out-file>.
[$self, $portFile, $outFile] = $argv;
$rejectRcpt = ($argv[3] ?? '') === 'reject-rcpt';
$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
file_put_contents($portFile, (string) parse_url('tcp://' . stream_socket_get_name($server, false), PHP_URL_PORT));
$conn = stream_socket_accept($server, 10);
$log = '';
$say = function ($line) use ($conn) { fwrite($conn, $line . "\r\n"); };
$say('220 fake ESMTP');
$data = false;
while (($line = fgets($conn)) !== false) {
    $line = rtrim($line, "\r\n");
    if ($data) {
        if ($line === '.') { $data = false; $say('250 queued'); continue; }
        $log .= 'DATA: ' . $line . "\n";
        continue;
    }
    $log .= 'C: ' . $line . "\n";
    $cmd = strtoupper(substr($line, 0, 4));
    if ($cmd === 'EHLO') { $say('250-fake'); $say('250 AUTH LOGIN'); }
    elseif ($cmd === 'AUTH') { $say('334 VXNlcm5hbWU6'); }
    elseif ($cmd === 'MAIL') { $say('250 ok'); }
    elseif ($cmd === 'RCPT') { $say($rejectRcpt ? '550 no such user' : '250 ok'); }
    elseif ($cmd === 'DATA') { $data = true; $say('354 go'); }
    elseif ($cmd === 'QUIT') { $say('221 bye'); break; }
    elseif (base64_decode($line, true) !== false) { $say(base64_decode($line) === 'smtp-user' ? '334 UGFzc3dvcmQ6' : '235 ok'); }
    else { $say('500 what'); }
}
file_put_contents($outFile, $log);
