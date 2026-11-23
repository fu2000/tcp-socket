<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Support;

/**
 * Thin config source abstraction so the core stays framework-agnostic.
 *
 * Keys use dot notation, e.g. "tcp-socket.tcp.server".
 */
interface ConfigRepositoryInterface
{
    public function get(string $key, mixed $default = null): mixed;

    /**
     * @return array<string, mixed>
     */
    public function all(): array;
}
