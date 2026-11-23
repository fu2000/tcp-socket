<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

/**
 * A runnable WebSocket server provided by a driver.
 */
interface WebSocketServerInterface
{
    public function setHandler(WebSocketServerHandlerInterface $handler): void;

    /**
     * Block and serve connections until stopped.
     */
    public function run(): void;

    public function stop(): void;

    /**
     * The address the server listens on, e.g. "ws://0.0.0.0:9502".
     */
    public function getAddress(): string;
}
