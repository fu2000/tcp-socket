<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Swoole;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\TcpClientInterface;
use Hk100\TcpSocket\Driver\Support\SwooleSupport;
use Hk100\TcpSocket\Framing\FramingInterface;
use Hk100\TcpSocket\Message\Message;

/**
 * Coroutine-based TCP client. MUST be used inside a coroutine (e.g. a Hyperf
 * coroutine). The "react" driver is the right choice outside coroutines.
 */
final class SwooleTcpClient implements TcpClientInterface
{
    private ?ClientHandlerInterface $handler = null;

    private FramingInterface $framing;

    private ?\Swoole\Coroutine\Client $client = null;

    private bool $connected = false;

    public function __construct(private readonly ClientConfig $config)
    {
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
        if (!SwooleSupport::inCoroutine()) {
            throw new \RuntimeException(
                'The Swoole TCP client requires a coroutine environment. Use the "react" driver outside coroutines.'
            );
        }

        $client = new \Swoole\Coroutine\Client(SWOOLE_SOCK_TCP);
        $client->set(['timeout' => (float) $this->config->connectTimeout]);

        if (!$client->connect($this->config->host, $this->config->port, (float) $this->config->connectTimeout)) {
            $this->handler?->onError(new \RuntimeException(
                'TCP connection failed (errCode ' . $client->errCode . ').',
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

    public function send(string $data): bool
    {
        if (!$this->connected || $this->client === null || !$this->client->isConnected()) {
            return false;
        }

        return (bool) $this->client->send($this->framing->encode($data));
    }

    public function close(): void
    {
        $this->connected = false;
        $this->client?->close();
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

    private function readLoop(\Swoole\Coroutine\Client $client): void
    {
        $remote = "{$this->config->host}:{$this->config->port}";

        while ($this->connected) {
            $data = $client->recv(-1);

            if ($data === false) {
                break;
            }
            if ($data === '') {
                break;
            }

            try {
                foreach ($this->framing->push($data) as $payload) {
                    $this->handler?->onMessage(new Message($payload, false, $remote));
                }
            } catch (\Throwable $exception) {
                $this->handler?->onError($exception);
                break;
            }
        }

        $this->connected = false;
        $this->handler?->onClose();
    }
}
