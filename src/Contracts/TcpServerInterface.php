<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

use Hk100\TcpSocket\Framing\FramingInterface;

/**
 * A runnable TCP server provided by a driver.
 */
interface TcpServerInterface
{
    public function setHandler(TcpServerHandlerInterface $handler): void;

    public function setFraming(FramingInterface $framing): void;

    /**
     * Block and serve connections until stopped.
     */
    public function run(): void;

    public function stop(): void;

    /**
     * The address the server listens on, e.g. "tcp://0.0.0.0:9501".
     */
    public function getAddress(): string;
}
