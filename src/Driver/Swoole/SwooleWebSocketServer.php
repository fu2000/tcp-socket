<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Swoole;

use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketServerInterface;
use Hk100\TcpSocket\Driver\Support\SwooleSupport;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\WebSocket\HandshakeServer;

/**
 * WebSocket server backed by Swoole.
 *
 * Must run in a non-coroutine process (Hyperf commands set $coroutine = false).
 */
final class SwooleWebSocketServer implements WebSocketServerInterface
{
    private ?WebSocketServerHandlerInterface $handler = null;

    private ?\Swoole\WebSocket\Server $server = null;

    /** @var array<int, SwooleConnection> */
    private array $connections = [];

    public function __construct(private readonly ServerConfig $config)
    {
    }

    public function setHandler(WebSocketServerHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function run(): void
    {
        $server = new \Swoole\WebSocket\Server(
            $this->config->host,
            $this->config->port,
            SWOOLE_PROCESS,
            SWOOLE_SOCK_TCP
        );
        $server->set(SwooleSupport::serverOptions($this->config->options));
        $this->server = $server;

        $server->on('handshake', function (\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool {
            return $this->handleHandshake($request, $response);
        });

        $server->on('open', function (\Swoole\WebSocket\Server $srv, \Swoole\Http\Request $request): void {
            $fd = $request->fd;
            $info = $srv->getClientInfo($fd);
            $remote = is_array($info) && isset($info['remote_ip'])
                ? $info['remote_ip'] . ':' . ($info['remote_port'] ?? 0)
                : null;

            $connection = new SwooleConnection(
                $fd,
                static fn (string $data): bool => (bool) $srv->push($fd, $data, WEBSOCKET_OPCODE_TEXT),
                static function () use ($srv, $fd): void {
                    $srv->close($fd);
                },
                static fn (): bool => (bool) $srv->isEstablished($fd),
                $remote,
                "{$this->config->host}:{$this->config->port}",
            );

            $this->connections[$fd] = $connection;
            $this->handler?->onOpen($connection);
        });

        $server->on('message', function (\Swoole\WebSocket\Server $srv, \Swoole\WebSocket\Frame $frame): void {
            if (!isset($this->connections[$frame->fd])) {
                return;
            }

            $connection = $this->connections[$frame->fd];

            if ($frame->opcode === WEBSOCKET_OPCODE_PING) {
                $srv->push($frame->fd, $frame->data, WEBSOCKET_OPCODE_PONG);
                return;
            }
            if ($frame->opcode === WEBSOCKET_OPCODE_PONG) {
                return;
            }

            try {
                $this->handler?->onMessage(
                    $connection,
                    new Message($frame->data, $frame->opcode === WEBSOCKET_OPCODE_BINARY, $connection->remoteAddress())
                );
            } catch (\Throwable $exception) {
                $this->handler?->onError($connection, $exception);
                $srv->close($frame->fd);
            }
        });

        $server->on('close', function (\Swoole\WebSocket\Server $srv, int $fd): void {
            if (!isset($this->connections[$fd])) {
                return;
            }

            $connection = $this->connections[$fd];
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
        return "ws://{$this->config->host}:{$this->config->port}{$this->config->path}";
    }

    private function handleHandshake(\Swoole\Http\Request $request, \Swoole\Http\Response $response): bool
    {
        $uri = $request->server['request_uri'] ?? '/';
        $path = (string) (strtok($uri, '?') ?: '/');

        if (!$this->pathMatches($path)) {
            $response->status(404);
            $response->end();
            return false;
        }

        $key = $request->header['sec-websocket-key'] ?? '';
        if ($key === '') {
            $response->status(400);
            $response->end();
            return false;
        }

        $accept = base64_encode(sha1($key . HandshakeServer::GUID, true));

        $response->header('Upgrade', 'websocket');
        $response->header('Connection', 'Upgrade');
        $response->header('Sec-WebSocket-Accept', $accept);
        $response->header('Sec-WebSocket-Version', '13');
        $response->status(101);
        $response->end();

        return true;
    }

    private function pathMatches(string $path): bool
    {
        $expected = $this->config->path;

        if ($expected === '' || $expected === '/') {
            return true;
        }

        return $path === $expected;
    }
}
