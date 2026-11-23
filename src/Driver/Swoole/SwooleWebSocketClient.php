<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Swoole;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketClientInterface;
use Hk100\TcpSocket\Driver\Support\SwooleSupport;
use Hk100\TcpSocket\Message\Message;

/**
 * Coroutine-based WebSocket client. MUST be used inside a coroutine.
 */
final class SwooleWebSocketClient implements WebSocketClientInterface
{
    private ?ClientHandlerInterface $handler = null;

    private ?\Swoole\Coroutine\Http\Client $client = null;

    private bool $connected = false;

    public function __construct(private readonly ClientConfig $config)
    {
    }

    public function setHandler(ClientHandlerInterface $handler): void
    {
        $this->handler = $handler;
    }

    public function connect(): void
    {
        if (!SwooleSupport::inCoroutine()) {
            throw new \RuntimeException(
                'The Swoole WebSocket client requires a coroutine environment. Use the "react" driver outside coroutines.'
            );
        }

        $client = new \Swoole\Coroutine\Http\Client(
            $this->config->host,
            $this->config->port,
            $this->config->secure
        );
        $client->set(['timeout' => (float) $this->config->connectTimeout]);

        if ($this->config->secure) {
            $client->set(['ssl_host_name' => $this->config->host]);
        }

        $client->setHeaders([
            'Host' => "{$this->config->host}:{$this->config->port}",
            'User-Agent' => 'hk100/tcp-socket',
        ]);

        if (!$client->upgrade($this->config->path)) {
            $this->handler?->onError(new \RuntimeException(
                'WebSocket handshake failed (errCode ' . $client->errCode . ').',
                (int) $client->errCode
            ));
            return;
        }

        $this->client = $client;
        $this->connected = true;
        $this->handler?->onConnect();

        \Swoole\Coroutine::create(function () use ($client): void {
            $this->readLoop($client);
        });
    }

    public function send(string $data, bool $binary = false): bool
    {
        if (!$this->connected || $this->client === null) {
            return false;
        }

        return (bool) $this->client->push(
            $data,
            $binary ? WEBSOCKET_OPCODE_BINARY : WEBSOCKET_OPCODE_TEXT
        );
    }

    public function close(): void
    {
        $this->connected = false;
        if ($this->client !== null) {
            $this->client->push('', WEBSOCKET_OPCODE_CLOSE);
            $this->client->close();
        }
        $this->client = null;
    }

    public function run(): void
    {
        while ($this->connected) {
            \Swoole\Coroutine::sleep(0.2);
        }
    }

    public function stop(): void
    {
        $this->close();
    }

    public function isConnected(): bool
    {
        return $this->connected;
    }

    private function readLoop(\Swoole\Coroutine\Http\Client $client): void
    {
        $remote = "{$this->config->host}:{$this->config->port}";
        $timeoutCode = defined('SOCKET_ETIMEDOUT') ? constant('SOCKET_ETIMEDOUT') : 110;

        while ($this->connected) {
            $frame = $client->recv(1.0);

            if ($frame === false) {
                if ((int) $client->errCode === (int) $timeoutCode) {
                    continue;
                }
                break;
            }

            $opcode = $frame->opcode;

            if ($opcode === WEBSOCKET_OPCODE_CLOSE) {
                break;
            }
            if ($opcode === WEBSOCKET_OPCODE_PING) {
                $client->push($frame->data, WEBSOCKET_OPCODE_PONG);
                continue;
            }
            if ($opcode === WEBSOCKET_OPCODE_PONG) {
                continue;
            }

            try {
                $this->handler?->onMessage(new Message(
                    $frame->data,
                    $opcode === WEBSOCKET_OPCODE_BINARY,
                    $remote
                ));
            } catch (\Throwable $exception) {
                $this->handler?->onError($exception);
                break;
            }
        }

        $this->connected = false;
        $this->handler?->onClose();
    }
}
