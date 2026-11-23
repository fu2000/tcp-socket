<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Swoole;

use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Contracts\TcpServerInterface;
use Hk100\TcpSocket\Driver\Support\SwooleSupport;
use Hk100\TcpSocket\Framing\FramingInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\ServerConfig;

/**
 * TCP server backed by Swoole.
 *
 * Must run in a non-coroutine process (Hyperf commands set $coroutine = false):
 * a Swoole\Server cannot be created while a coroutine event loop is active.
 */
final class SwooleTcpServer implements TcpServerInterface
{
    private ?TcpServerHandlerInterface $handler = null;

    private FramingInterface $framingTemplate;

    private ?\Swoole\Server $server = null;

    /** @var array<int, array{0: SwooleConnection, 1: FramingInterface}> */
    private array $connections = [];

    public function __construct(private readonly ServerConfig $config)
    {
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
        $server = new \Swoole\Server(
            $this->config->host,
            $this->config->port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP
        );
        $server->set(SwooleSupport::serverOptions($this->config->options));
        $this->server = $server;

        $server->on('connect', function (\Swoole\Server $srv, int $fd): void {
            $framing = clone $this->framingTemplate;
            $framing->reset();

            $connection = $this->makeConnection($srv, $fd, $framing);
            $this->connections[$fd] = [$connection, $framing];
            $this->handler?->onOpen($connection);
        });

        $server->on('receive', function (\Swoole\Server $srv, int $fd, int $reactorId, string $data): void {
            if (!isset($this->connections[$fd])) {
                return;
            }

            [$connection, $framing] = $this->connections[$fd];

            try {
                foreach ($framing->push($data) as $payload) {
                    $this->handler?->onMessage(
                        $connection,
                        new Message($payload, false, $connection->remoteAddress())
                    );
                }
            } catch (\Throwable $exception) {
                $this->handler?->onError($connection, $exception);
                $srv->close($fd);
            }
        });

        $server->on('close', function (\Swoole\Server $srv, int $fd): void {
            if (!isset($this->connections[$fd])) {
                return;
            }

            [$connection] = $this->connections[$fd];
            unset($this->connections[$fd]);
            $this->handler?->onClose($connection);
        });

        $server->start();
    }

    public function stop(): void
    {
        $this->server?->shutdown();
    }

    public function getAddress(): string
    {
        return "tcp://{$this->config->host}:{$this->config->port}";
    }

    private function makeConnection(\Swoole\Server $server, int $fd, FramingInterface $framing): SwooleConnection
    {
        $info = $server->getClientInfo($fd);
        $remote = is_array($info) && isset($info['remote_ip'])
            ? $info['remote_ip'] . ':' . ($info['remote_port'] ?? 0)
            : null;

        return new SwooleConnection(
            $fd,
            static fn (string $data): bool => (bool) $server->send($fd, $framing->encode($data)),
            static function () use ($server, $fd): void {
                $server->close($fd);
            },
            static fn (): bool => (bool) $server->exist($fd),
            $remote,
            "{$this->config->host}:{$this->config->port}",
        );
    }
}
