<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests\Support;

use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Message\Message;
use React\EventLoop\LoopInterface;

/**
 * Test double that records messages and can stop a loop after the first one.
 */
final class CollectingClientHandler implements ClientHandlerInterface
{
    public ?string $message = null;

    public int $count = 0;

    public ?\Closure $onConnect = null;

    public ?\Closure $onError = null;

    public function __construct(private readonly ?LoopInterface $loop = null)
    {
    }

    public function onConnect(): void
    {
        if ($this->onConnect !== null) {
            ($this->onConnect)();
        }
    }

    public function onMessage(Message $message): void
    {
        $this->message = $message->payload;
        ++$this->count;
        $this->loop?->stop();
    }

    public function onClose(): void
    {
    }

    public function onError(\Throwable $exception): void
    {
        if ($this->onError !== null) {
            ($this->onError)($exception);
        }
    }
}
