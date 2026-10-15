<?php
declare(strict_types=1);

function mail_configured(): bool { return (string)env('MAIL_HOST', '') !== '' && (string)env('MAIL_FROM', '') !== ''; }

/** Minimal SMTP client (TLS/STARTTLS + AUTH LOGIN). Returns false when mail is not configured or sending fails. */
function send_mail(string $to, string $subject, string $body): bool {
    if (!mail_configured()) return false;
    $host = (string)env('MAIL_HOST'); $port = (int)env('MAIL_PORT', 587); $enc = strtolower((string)env('MAIL_ENCRYPTION', 'tls'));
    $from = (string)env('MAIL_FROM'); $fromName = (string)env('MAIL_FROM_NAME', 'Voice of Women in Tech');
    $fp = @stream_socket_client(($enc === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) { app_log("SMTP connect failed: $errstr"); return false; }
    stream_set_timeout($fp, 15);
    $read = function () use ($fp): string { $o = ''; while (($l = fgets($fp, 515)) !== false) { $o .= $l; if (strlen($l) < 4 || $l[3] === ' ') break; } return $o; };
    $cmd = function (string $c) use ($fp, $read): string { fwrite($fp, $c . "\r\n"); return $read(); };
    $ok = fn(string $r, string $code): bool => strncmp($r, $code, 3) === 0;
    try {
        if (!$ok($read(), '220')) throw new RuntimeException('bad greeting');
        $ehlo = 'EHLO ' . (parse_url((string)env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost');
        if (!$ok($cmd($ehlo), '250')) throw new RuntimeException('EHLO failed');
        if ($enc === 'tls') {
            if (!$ok($cmd('STARTTLS'), '220')) throw new RuntimeException('STARTTLS failed');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) throw new RuntimeException('TLS failed');
            if (!$ok($cmd($ehlo), '250')) throw new RuntimeException('EHLO2 failed');
        }
        if ((string)env('MAIL_USER', '') !== '') {
            if (!$ok($cmd('AUTH LOGIN'), '334')) throw new RuntimeException('AUTH not accepted');
            if (!$ok($cmd(base64_encode((string)env('MAIL_USER'))), '334')) throw new RuntimeException('user rejected');
            if (!$ok($cmd(base64_encode((string)env('MAIL_PASS', ''))), '235')) throw new RuntimeException('login rejected');
        }
        if (!$ok($cmd('MAIL FROM:<' . $from . '>'), '250')) throw new RuntimeException('MAIL FROM rejected');
        if (!$ok($cmd('RCPT TO:<' . $to . '>'), '250')) throw new RuntimeException('RCPT rejected');
        if (!$ok($cmd('DATA'), '354')) throw new RuntimeException('DATA rejected');
        $headers = 'From: =?UTF-8?B?' . base64_encode($fromName) . '?= <' . $from . ">\r\nTo: <" . $to . ">\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nDate: " . gmdate('r') . "\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n";
        $msg = $headers . "\r\n" . chunk_split(base64_encode($body));
        if (!$ok($cmd($msg . "\r\n."), '250')) throw new RuntimeException('message rejected');
        $cmd('QUIT'); fclose($fp);
        return true;
    } catch (Throwable $e) { app_log('SMTP error: ' . $e->getMessage()); @fclose($fp); return false; }
}
