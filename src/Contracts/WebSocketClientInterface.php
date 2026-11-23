<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

/**
 * A WebSocket client transport provided by a driver.
 */
interface WebSocketClientInterface
{
    public function setHandler(ClientHandlerInterface $handler): void;

    public function connect(): void;

    public function send(string $data, bool $binary = false): bool;

    public function close(): void;

    public function run(): void;

    public function stop(): void;

    public function isConnected(): bool;
}
