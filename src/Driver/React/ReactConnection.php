<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\React;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use React\Socket\ConnectionInterface as ReactStream;

/**
 * Adapts a ReactPHP socket stream to the package connection contract.
 *
 * Outbound data is passed through the supplied encoder so handlers always work
 * in message-space (framing / WebSocket framing is applied transparently).
 */
final class ReactConnection implements ConnectionInterface
{
    /** @var array<string, mixed> */
    private array $context = [];

    private bool $closed = false;

    /**
     * @param \Closure(string): string $encoder
     */
    public function __construct(
        private readonly int $id,
        private readonly ReactStream $stream,
        private readonly \Closure $encoder,
        private readonly ?string $closeFrame = null,
    ) {
        $stream->on('close', function (): void {
            $this->closed = true;
        });
    }

    public function id(): int|string
    {
        return $this->id;
    }

    public function remoteAddress(): ?string
    {
        return $this->stream->getRemoteAddress();
    }

    public function localAddress(): ?string
    {
        return $this->stream->getLocalAddress();
    }

    public function send(string $data): bool
    {
        if ($this->closed || !$this->stream->isWritable()) {
            return false;
        }

        $this->stream->write(($this->encoder)($data));

        return true;
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }

        if ($this->closeFrame !== null && $this->stream->isWritable()) {
            $this->stream->write($this->closeFrame);
        }

        $this->stream->end();
    }

    public function isConnected(): bool
    {
        return !$this->closed && $this->stream->isReadable();
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
