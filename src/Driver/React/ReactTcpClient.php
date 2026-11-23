<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\React;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\TcpClientInterface;
use Hk100\TcpSocket\Framing\FramingInterface;
use Hk100\TcpSocket\Message\Message;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactStream;
use React\Socket\Connector;

final class ReactTcpClient implements TcpClientInterface
{
    private ?ClientHandlerInterface $handler = null;

    private FramingInterface $framing;

    private readonly LoopInterface $loop;

    private ?ReactStream $stream = null;

    private bool $connected = false;

    public function __construct(private readonly ClientConfig $config, ?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
        $this->framing = $config->framing();
    }

    public function setHandler(ClientHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function setFraming(FramingInterface $framing): void
    {
        $this->framing = $framing;
    }

    public function connect(): void
    {
        $connector = new Connector(['timeout' => (float) $this->config->connectTimeout], $this->loop);

        $connector->connect($this->config->tcpUri())->then(
            function (ReactStream $stream): void {
                $this->stream = $stream;
                $this->connected = true;

                $stream->on('data', function (string $chunk) use ($stream): void {
                    try {
                        foreach ($this->framing->push($chunk) as $payload) {
                            $this->handler?->onMessage(new Message($payload, false, $stream->getRemoteAddress()));
                        }
                    } catch (\Throwable $exception) {
                        $this->handler?->onError($exception);
                    }
                });

                $stream->on('close', function (): void {
                    $this->connected = false;
                    $this->handler?->onClose();
                });

                $stream->on('error', function (\Throwable $exception): void {
                    $this->handler?->onError($exception);
                });

                $this->handler?->onConnect();
            },
            function (\Throwable $exception): void {
                $this->connected = false;
                $this->handler?->onError($exception);
            }
        );
    }

    public function send(string $data): bool
    {
        if (!$this->connected || $this->stream === null || !$this->stream->isWritable()) {
            return false;
        }

        $this->stream->write($this->framing->encode($data));

        return true;
    }

    public function close(): void
    {
        $this->connected = false;
        $this->stream?->end();
    }

    public function run(): void
    {
        $this->loop->run();
    }

    public function stop(): void
    {
        $this->connected = false;
        $this->stream?->close();
        $this->loop->stop();
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }
}
