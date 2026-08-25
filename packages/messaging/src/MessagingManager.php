<?php

declare(strict_types=1);

namespace Tihloh\Prefab\Messaging;

use InvalidArgumentException;
use Tihloh\Prefab\Messaging\Contracts\ChannelInterface;
use Tihloh\Prefab\Messaging\Contracts\NotificationInterface;

final class MessagingManager
{
    /** @var array<string, ChannelInterface> */
    private array $channels = [];

    /** @var array<string, list<callable>> */
    private array $hooks = [];

    /** @param iterable<ChannelInterface> $channels */
    public function __construct(iterable $channels = [])
    {
        foreach ($channels as $channel) {
            $this->channel($channel);
        }
    }

    public function channel(ChannelInterface $channel): self
    {
        $this->channels[$channel->name()] = $channel;
        return $this;
    }

    public function hasChannel(string $name): bool
    {
        return isset($this->channels[$name]);
    }

    public function send(string $channel, Recipient|string $recipient, Message $message): DeliveryResult
    {
        $recipient = is_string($recipient) && $channel === 'mail'
            ? Recipient::email($recipient)
            : $recipient;

        if (!$recipient instanceof Recipient) {
            throw new InvalidArgumentException('A Recipient object is required for non-mail channels.');
        }

        $driver = $this->channels[$channel] ?? null;
        if (!$driver) {
            throw new InvalidArgumentException("Messaging channel [{$channel}] is not registered.");
        }

        $this->emit('sending', $channel, $recipient, $message);
        $result = $driver->send($recipient, $message);
        $this->emit($result->successful ? 'sent' : 'failed', $channel, $recipient, $message, $result);

        return $result;
    }

    public function mail(string $to, string $subject, string $body, ?string $html = null): DeliveryResult
    {
        return $this->send('mail', $to, new Message($subject, $body, $html));
    }

    /** @return list<DeliveryResult> */
    public function notify(Recipient $recipient, NotificationInterface $notification): array
    {
        $results = [];
        foreach ($notification->channels($recipient) as $channel) {
            $results[] = $this->send($channel, $recipient, $notification->message($channel, $recipient));
        }
        return $results;
    }

    public function on(string $event, callable $listener): self
    {
        $this->hooks[$event][] = $listener;
        return $this;
    }

    private function emit(string $event, mixed ...$arguments): void
    {
        foreach ($this->hooks[$event] ?? [] as $listener) {
            $listener(...$arguments);
        }
    }
}
