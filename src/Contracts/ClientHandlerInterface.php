<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

use Hk100\TcpSocket\Message\Message;

/**
 * Lifecycle callbacks delivered to a client transport. Extend one of the
 * Abstract*Client base classes which implement this interface.
 */
interface ClientHandlerInterface
{
    public function onConnect(): void;

    public function onMessage(Message $message): void;

    public function onClose(): void;

    public function onError(\Throwable $exception): void;
}
