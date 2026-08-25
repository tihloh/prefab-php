<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Mail;

use RuntimeException;
use Tihloh\Prefab\Messaging\Attachment;
use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class SmtpTransport implements MailTransportInterface
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public function send(Recipient $recipient, Message $message): DeliveryResult
    {
        $to = $recipient->route('mail');
        if (!is_string($to) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return new DeliveryResult(false, 'mail', error: 'Recipient has no valid mail route.');
        }

        try {
            $socket = $this->connect();
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO ' . ($this->config['helo'] ?? 'localhost'), [250]);

            if (($this->config['encryption'] ?? null) === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('Unable to enable SMTP TLS encryption.');
                }
                $this->command($socket, 'EHLO ' . ($this->config['helo'] ?? 'localhost'), [250]);
            }

            if (!empty($this->config['username'])) {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode((string) $this->config['username']), [334], false);
                $this->command($socket, base64_encode((string) ($this->config['password'] ?? '')), [235], false);
            }

            $from = (string) ($this->config['from']['address'] ?? $this->config['from'] ?? $this->config['username'] ?? '');
            if (!filter_var($from, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('SMTP from address is missing or invalid.');

            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            foreach (array_unique([$to, ...$message->cc, ...$message->bcc]) as $address) {
                if (filter_var($address, FILTER_VALIDATE_EMAIL)) $this->command($socket, 'RCPT TO:<' . $address . '>', [250, 251]);
            }
            $this->command($socket, 'DATA', [354]);
            fwrite($socket, $this->mime($to, $from, $message) . "\r\n.\r\n");
            $response = $this->expect($socket, [250]);
            $this->command($socket, 'QUIT', [221]);
            fclose($socket);

            return new DeliveryResult(true, 'mail', $this->messageId($response));
        } catch (\Throwable $e) {
            return new DeliveryResult(false, 'mail', error: $e->getMessage());
        }
    }

    /** @return resource */
    private function connect()
    {
        $host = (string) ($this->config['host'] ?? 'localhost');
        $port = (int) ($this->config['port'] ?? 587);
        $scheme = ($this->config['encryption'] ?? null) === 'ssl' ? 'ssl://' : '';
        $timeout = (float) ($this->config['timeout'] ?? 10);
        $socket = @stream_socket_client($scheme . $host . ':' . $port, $errno, $error, $timeout);
        if (!$socket) throw new RuntimeException("SMTP connection failed: {$error} ({$errno}).");
        stream_set_timeout($socket, (int) ceil($timeout));
        return $socket;
    }

    /** @param resource $socket @param list<int> $codes */
    private function command($socket, string $command, array $codes, bool $loggable = true): string
    {
        fwrite($socket, $command . "\r\n");
        return $this->expect($socket, $codes);
    }

    /** @param resource $socket @param list<int> $codes */
    private function expect($socket, array $codes): string
    {
        $response = '';
        do {
            $line = fgets($socket, 8192);
            if ($line === false) throw new RuntimeException('SMTP server closed the connection unexpectedly.');
            $response .= $line;
        } while (isset($line[3]) && $line[3] === '-');
        $code = (int) substr($line, 0, 3);
        if (!in_array($code, $codes, true)) throw new RuntimeException('SMTP error: ' . trim($response));
        return trim($response);
    }

    private function mime(string $to, string $from, Message $message): string
    {
        $fromName = is_array($this->config['from'] ?? null) ? ($this->config['from']['name'] ?? null) : null;
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($fromName ? $this->encode($fromName) . " <{$from}>" : $from),
            'To: ' . $to,
            'Subject: ' . $this->encode($message->subject),
            'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . ($this->config['message_id_domain'] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
        ];
        if ($message->cc) $headers[] = 'Cc: ' . implode(', ', $message->cc);
        if ($message->replyTo) $headers[] = 'Reply-To: ' . $message->replyTo;

        if (!$message->attachments && $message->html === null) {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: quoted-printable';
            return implode("\r\n", $headers) . "\r\n\r\n" . quoted_printable_encode($message->text);
        }

        $mixed = 'mix_' . bin2hex(random_bytes(12));
        $alt = 'alt_' . bin2hex(random_bytes(12));
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mixed . '"';
        $body = '--' . $mixed . "\r\n";

        if ($message->html !== null) {
            $body .= 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . "\r\n\r\n";
            $body .= '--' . $alt . "\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($message->text) . "\r\n";
            $body .= '--' . $alt . "\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($message->html) . "\r\n--{$alt}--\r\n";
        } else {
            $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: quoted-printable\r\n\r\n" . quoted_printable_encode($message->text) . "\r\n";
        }

        foreach ($message->attachments as $attachment) {
            if (!$attachment instanceof Attachment) continue;
            $mime = $attachment->mime ?? 'application/octet-stream';
            $name = addcslashes($attachment->name, "\"\\");
            $body .= '--' . $mixed . "\r\nContent-Type: {$mime}; name=\"{$name}\"\r\nContent-Disposition: attachment; filename=\"{$name}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n";
            $body .= chunk_split(base64_encode($attachment->data()), 76, "\r\n");
        }
        return implode("\r\n", $headers) . "\r\n\r\n" . $body . '--' . $mixed . '--';
    }

    private function encode(string $value): string
    {
        return '=?UTF-8?B?' . base64_encode($value) . '?=';
    }

    private function messageId(string $response): ?string
    {
        return preg_match('/(?:queued as|id=?)\s*<?([^\s>]+)/i', $response, $m) ? $m[1] : null;
    }
}
