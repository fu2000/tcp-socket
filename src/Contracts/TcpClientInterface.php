<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

use Hk100\TcpSocket\Framing\FramingInterface;

/**
 * A TCP client transport provided by a driver.
 */
interface TcpClientInterface
{
    public function setHandler(ClientHandlerInterface $handler): void;

    public function setFraming(FramingInterface $framing): void;

    /**
     * Initiate the connection. Async drivers connect in the background and
     * report readiness through {@see ClientHandlerInterface::onConnect()}.
     */
    public function connect(): void;

    public function send(string $data): bool;

    public function close(): void;

    /**
     * Block and pump the client transport until it disconnects or stops.
     */
    public function run(): void;

    public function stop(): void;

    public function isConnected(): bool;
}
