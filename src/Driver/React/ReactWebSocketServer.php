<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\React;

use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketServerInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\WebSocket\FrameEncoder;
use Hk100\TcpSocket\WebSocket\FrameParser;
use Hk100\TcpSocket\WebSocket\HandshakeServer;
use Hk100\TcpSocket\WebSocket\Opcode;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactStream;
use React\Socket\SocketServer;

final class ReactWebSocketServer implements WebSocketServerInterface
{
    private ?WebSocketServerHandlerInterface $handler = null;

    private ?SocketServer $server = null;

    private readonly LoopInterface $loop;

    private int $nextId = 1;

    /** @var array<int, ReactConnection> */
    private array $connections = [];

    /** @var array<int, ReactStream> */
    private array $streams = [];

    /** @var array<int, float> */
    private array $lastSeen = [];

    public function __construct(private readonly ServerConfig $config, ?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
    }

    public function setHandler(WebSocketServerHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function run(): void
    {
        $this->server = new SocketServer("tcp://{$this->config->host}:{$this->config->port}", [], $this->loop);
        $this->server->on('connection', function (ReactStream $stream): void {
            $this->handleConnection($stream);
        });
        $this->server->on('error', static function (): void {
        });

        if ($this->config->heartbeatInterval > 0) {
            $this->loop->addPeriodicTimer($this->config->heartbeatInterval, function (): void {
                $deadline = microtime(true) - $this->config->heartbeatTimeout;
                foreach ($this->connections as $id => $connection) {
                    if (($this->lastSeen[$id] ?? 0) <= $deadline) {
                        $connection->close();
                        continue;
                    }
                    if (isset($this->streams[$id])) {
                        $this->streams[$id]->write(FrameEncoder::ping('', false));
                    }
                }
            });
        }

        $this->loop->run();
    }

    public function stop(): void
    {
        foreach ($this->connections as $connection) {
            $connection->close();
        }
        $this->connections = [];
        $this->streams = [];
        $this->lastSeen = [];

        $this->server?->close();
        $this->server = null;
        $this->loop->stop();
    }

    public function getAddress(): string
    {
        return "ws://{$this->config->host}:{$this->config->port}{$this->config->path}";
    }

    private function handleConnection(ReactStream $stream): void
    {
        $id = $this->nextId++;
        $buffer = '';
        $handshaken = false;
        $parser = new FrameParser(requireMask: true);
        $connection = null;

        $open = function () use (&$connection, $id, $stream): void {
            $connection = new ReactConnection(
                $id,
                $stream,
                static fn (string $data): string => FrameEncoder::encode($data, Opcode::Text, false),
                FrameEncoder::close(1000, false),
            );
            $this->connections[$id] = $connection;
            $this->streams[$id] = $stream;
            $this->lastSeen[$id] = microtime(true);

            $stream->on('close', function () use ($id, &$connection): void {
                unset($this->connections[$id], $this->streams[$id], $this->lastSeen[$id]);
                if ($connection !== null) {
                    $this->handler?->onClose($connection);
                }
            });
            $stream->on('error', function (\Throwable $exception) use (&$connection): void {
                if ($connection !== null) {
                    $this->handler?->onError($connection, $exception);
                }
            });

            $this->handler?->onOpen($connection);
        };

        $processFrames = function (string $chunk) use ($parser, $id, &$connection, $stream): void {
            try {
                foreach ($parser->push($chunk) as $frame) {
                    $this->lastSeen[$id] = microtime(true);

                    if ($frame->opcode === Opcode::Ping) {
                        $stream->write(FrameEncoder::pong($frame->payload, false));
                        continue;
                    }
                    if ($frame->opcode === Opcode::Pong) {
                        continue;
                    }
                    if ($frame->opcode === Opcode::Close) {
                        $stream->write(FrameEncoder::close(1000, false));
                        $stream->end();
                        continue;
                    }
                    if ($connection !== null) {
                        $this->handler?->onMessage(
                            $connection,
                            new Message($frame->payload, $frame->isBinary(), $connection->remoteAddress())
                        );
                    }
                }
            } catch (\Throwable $exception) {
                if ($connection !== null) {
                    $this->handler?->onError($connection, $exception);
                }
                $stream->end();
            }
        };

        $stream->on('data', function (string $chunk) use (&$buffer, &$handshaken, $open, $processFrames, $stream): void {
            if ($handshaken) {
                $processFrames($chunk);
                return;
            }

            $buffer .= $chunk;
            $pos = strpos($buffer, "\r\n\r\n");
            if ($pos === false) {
                if (strlen($buffer) > 16384) {
                    $stream->write(HandshakeServer::buildRejectResponse(431, 'Request Header Fields Too Large'));
                    $stream->end();
                }
                return;
            }

            $headers = substr($buffer, 0, $pos + 4);
            $remaining = substr($buffer, $pos + 4);
            $buffer = '';

            $path = HandshakeServer::getPath($headers);
            if (!$this->pathMatches($path)) {
                $stream->write(HandshakeServer::buildRejectResponse(404, 'Not Found'));
                $stream->end();
                return;
            }

            $response = HandshakeServer::buildAcceptResponse($headers);
            if ($response === null) {
                $stream->write(HandshakeServer::buildRejectResponse());
                $stream->end();
                return;
            }

            $stream->write($response);
            $handshaken = true;
            $open();

            if ($remaining !== '') {
                $processFrames($remaining);
            }
        });
    }

    private function pathMatches(?string $path): bool
    {
        $expected = $this->config->path;

        if ($expected === '' || $expected === '/') {
            return true;
        }

        return $path === $expected;
    }
}
