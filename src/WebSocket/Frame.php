<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\WebSocket;

final class Frame
{
    public function __construct(
        public readonly Opcode $opcode,
        public readonly string $payload,
        public readonly bool $fin = true,
    ) {
    }

    public function isData(): bool
    {
        return $this->opcode === Opcode::Text || $this->opcode === Opcode::Binary;
    }

    public function isBinary(): bool
    {
        return $this->opcode === Opcode::Binary;
    }
}
