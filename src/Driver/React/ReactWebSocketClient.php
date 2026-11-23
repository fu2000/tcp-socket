<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\React;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketClientInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\WebSocket\FrameEncoder;
use Hk100\TcpSocket\WebSocket\FrameParser;
use Hk100\TcpSocket\WebSocket\HandshakeClient;
use Hk100\TcpSocket\WebSocket\Opcode;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactStream;
use React\Socket\Connector;

final class ReactWebSocketClient implements WebSocketClientInterface
{
    private ?ClientHandlerInterface $handler = null;

    private readonly LoopInterface $loop;

    private ?ReactStream $stream = null;

    private bool $connected = false;

    private string $key = '';

    private string $buffer = '';

    private FrameParser $parser;

    public function __construct(private readonly ClientConfig $config, ?LoopInterface $loop = null)
    {
        $this->loop = $loop ?? Loop::get();
        $this->parser = new FrameParser(requireMask: false);
    }

    public function setHandler(ClientHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function connect(): void
    {
        $connector = new Connector(['timeout' => (float) $this->config->connectTimeout], $this->loop);
        $scheme = $this->config->secure ? 'tls' : 'tcp';
        $uri = "{$scheme}://{$this->config->host}:{$this->config->port}";

        $connector->connect($uri)->then(
            function (ReactStream $stream): void {
                $this->stream = $stream;
                $this->key = HandshakeClient::generateKey();

                $stream->write(HandshakeClient::buildRequest(
                    $this->config->host,
                    $this->config->port,
                    $this->config->path,
                    $this->key
                ));

                $stream->on('data', function (string $chunk) use ($stream): void {
                    if ($this->connected) {
                        $this->consumeFrames($chunk, $stream);
                        return;
                    }

                    $this->buffer .= $chunk;
                    $pos = strpos($this->buffer, "\r\n\r\n");
                    if ($pos === false) {
                        return;
                    }

                    $response = substr($this->buffer, 0, $pos + 4);
                    $remaining = substr($this->buffer, $pos + 4);
                    $this->buffer = '';

                    if (!HandshakeClient::verifyResponse($response, $this->key)) {
                        $this->handler?->onError(new \RuntimeException('WebSocket handshake failed.'));
                        $stream->end();
                        return;
                    }

                    $this->connected = true;
                    $this->handler?->onConnect();

                    if ($remaining !== '') {
                        $this->consumeFrames($remaining, $stream);
                    }
                });

                $stream->on('close', function (): void {
                    $this->connected = false;
                    $this->handler?->onClose();
                });

                $stream->on('error', function (\Throwable $exception): void {
                    $this->handler?->onError($exception);
                });
            },
            function (\Throwable $exception): void {
                $this->connected = false;
                $this->handler?->onError($exception);
            }
        );
    }

    public function send(string $data, bool $binary = false): bool
    {
        if (!$this->connected || $this->stream === null || !$this->stream->isWritable()) {
            return false;
        }

        $this->stream->write(FrameEncoder::encode($data, $binary ? Opcode::Binary : Opcode::Text, true));

        return true;
    }

    public function close(): void
    {
        if ($this->stream !== null && $this->connected && $this->stream->isWritable()) {
            $this->stream->write(FrameEncoder::close(1000, true));
        }
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

    private function consumeFrames(string $chunk, ReactStream $stream): void
    {
        try {
            foreach ($this->parser->push($chunk) as $frame) {
                if ($frame->opcode === Opcode::Ping) {
                    $stream->write(FrameEncoder::pong($frame->payload, true));
                    continue;
                }
                if ($frame->opcode === Opcode::Pong) {
                    continue;
                }
                if ($frame->opcode === Opcode::Close) {
                    $stream->end();
                    continue;
                }
                $this->handler?->onMessage(new Message($frame->payload, $frame->isBinary(), $stream->getRemoteAddress()));
            }
        } catch (\Throwable $exception) {
            $this->handler?->onError($exception);
            $stream->end();
        }
    }
}
