<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Swoole;

use Hk100\TcpSocket\Contracts\ConnectionInterface;

/**
 * Adapts a Swoole server connection ($fd) to the package connection contract.
 */
final class SwooleConnection implements ConnectionInterface
{
    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param \Closure(string): bool $sender Applies framing and writes to the fd.
     * @param \Closure(): void      $closer
     * @param \Closure(): bool      $exists
     */
    public function __construct(
        private readonly int $fd,
        private readonly \Closure $sender,
        private readonly \Closure $closer,
        private readonly \Closure $exists,
        private readonly ?string $remote,
        private readonly ?string $local,
    ) {
    }

    public function id(): int|string
    {
        return $this->fd;
    }

    public function remoteAddress(): ?string
    {
        return $this->remote;
    }

    public function localAddress(): ?string
    {
        return $this->local;
    }

    public function send(string $data): bool
    {
        return (bool) ($this->sender)($data);
    }

    public function close(): void
    {
        ($this->closer)();
    }

    public function isConnected(): bool
    {
        return (bool) ($this->exists)();
    }

    public function setContext(string $key, mixed $value): void
    {
        $this->context[$key] = $value;
    }

    public function getContext(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    public function allContext(): array
    {
        return $this->context;
    }
}
