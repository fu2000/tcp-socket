<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

/**
 * A live peer connection as seen by a running server.
 *
 * Implementations are provided by the drivers; handlers receive them and use
 * them to send data or attach per-connection state (e.g. a device id after
 * the handshake).
 */
interface ConnectionInterface
{
    public function id(): int|string;

    public function remoteAddress(): ?string;

    public function localAddress(): ?string;

    public function send(string $data): bool;

    public function close(): void;

    public function isConnected(): bool;

    public function setContext(string $key, mixed $value): void;

    public function getContext(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function allContext(): array;
}
