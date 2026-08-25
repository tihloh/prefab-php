<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging\Mail;

use Tihloh\Prefab\Messaging\DeliveryResult;
use Tihloh\Prefab\Messaging\Message;
use Tihloh\Prefab\Messaging\Recipient;

final class NativeMailTransport implements MailTransportInterface
{
    public function __construct(
        private readonly ?string $from = null,
        private readonly ?string $fromName = null,
    ) {}

    public function send(Recipient $recipient, Message $message): DeliveryResult
    {
        $to = $recipient->route('mail');
        if (!is_string($to) || $to === '') {
            return new DeliveryResult(false, 'mail', error: 'Recipient has no mail route.');
        }

        $headers = ['MIME-Version: 1.0'];
        if ($this->from) {
            $from = $this->fromName ? sprintf('%s <%s>', $this->fromName, $this->from) : $this->from;
            $headers[] = 'From: ' . $from;
        }
        if ($message->replyTo) $headers[] = 'Reply-To: ' . $message->replyTo;
        if ($message->cc) $headers[] = 'Cc: ' . implode(', ', $message->cc);
        if ($message->bcc) $headers[] = 'Bcc: ' . implode(', ', $message->bcc);

        // Native mail is intentionally the zero-dependency fallback. Rich multipart
        // attachments should use a dedicated SMTP/provider transport adapter.
        if ($message->attachments) {
            return new DeliveryResult(false, 'mail', error: 'NativeMailTransport does not support attachments; use an SMTP/provider transport.');
        }

        if ($message->html !== null) {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $body = $message->html;
        } else {
            $headers[] = 'Content-Type: text/plain; charset=UTF-8';
            $body = $message->text;
        }

        $ok = @mail($to, $message->subject, $body, implode("\r\n", $headers));
        return new DeliveryResult($ok, 'mail', error: $ok ? null : 'PHP mail() returned false.');
    }
}
