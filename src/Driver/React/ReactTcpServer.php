<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\React;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Contracts\TcpServerInterface;
use Hk100\TcpSocket\Framing\FramingInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\ServerConfig;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactStream;
use React\Socket\SocketServer;

final class ReactTcpServer implements TcpServerInterface
{
    private ?TcpServerHandlerInterface $handler = null;

    private FramingInterface $framingTemplate;

    private ?SocketServer $server = null;

    private readonly LoopInterface $loop;

    private int $nextId = 1;

    /** @var array<int, ConnectionInterface> */
    private array $connections = [];

    public function __construct(private readonly ServerConfig $config, ?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
        $this->framingTemplate = $config->framing();
    }

    public function setHandler(TcpServerHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function setFraming(FramingInterface $framing): void
    {
        $this->framingTemplate = $framing;
    }

    public function run(): void
    {
        $this->server = new SocketServer("tcp://{$this->config->host}:{$this->config->port}", [], $this->loop);
        $this->server->on('connection', function (ReactStream $stream): void {
            $this->handleConnection($stream);
        });

        $this->loop->run();
    }

    public function stop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        $this->connections = [];

        $this->server?->close();
        $this->server = null;
        $this->loop->stop();
    }

    public function getAddress(): string
    {
        return "tcp://{$this->config->host}:{$this->config->port}";
    }

    private function handleConnection(ReactStream $stream): void
    {
        $id = $this->nextId++;
        $framing = clone $this->framingTemplate;
        $framing->reset();

        $connection = new ReactConnection($id, $stream, static fn (string $data): string => $framing->encode($data));
        $this->connections[$id] = $connection;

        $stream->on('data', function (string $chunk) use ($connection, $framing): void {
            try {
                foreach ($framing->push($chunk) as $payload) {
                    $this->handler?->onMessage(
                        $connection,
                        new Message($payload, false, $connection->remoteAddress())
                    );
                }
            } catch (\Throwable $exception) {
                $this->handler?->onError($connection, $exception);
                $connection->close();
            }
        });

        $stream->on('close', function () use ($id, $connection): void {
            unset($this->connections[$id]);
            $this->handler?->onClose($connection);
        });

        $stream->on('error', function (\Throwable $exception) use ($connection): void {
            $this->handler?->onError($connection, $exception);
        });

        $this->handler?->onOpen($connection);
    }
}
