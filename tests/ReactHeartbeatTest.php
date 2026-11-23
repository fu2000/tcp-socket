<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ClientHandlerInterface;
use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Driver\React\ReactTcpClient;
use Hk100\TcpSocket\Driver\React\ReactTcpServer;
use Hk100\TcpSocket\Driver\React\ReactWebSocketClient;
use Hk100\TcpSocket\Driver\React\ReactWebSocketServer;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Tests\Support\CollectingClientHandler;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;

final class ReactHeartbeatTest extends TestCase
{
    public function testWebSocketPingsKeepIdleClientAlive(): void
    {
        $port = $this->freePort();
        $loop = new StreamSelectLoop();

        $server = new ReactWebSocketServer(ServerConfig::forWebSocket([
            'host' => '127.0.0.1',
            'port' => $port,
            'path' => '/',
            'heartbeat' => ['interval' => 1, 'timeout' => 2],
        ]), $loop);
        $server->setHandler(new class extends AbstractWebSocketServerHandler {
            public function onMessage(ConnectionInterface $connection, Message $message): void
            {
                $connection->send('echo: ' . $message->payload);
            }
        });

        $handler = new CollectingClientHandler($loop);
        $client = new ReactWebSocketClient(
            ClientConfig::forWebSocket(['url' => "ws://127.0.0.1:{$port}"]),
            $loop
        );
        $client->setHandler($handler);

        $loop->addTimer(0.1, static fn () => $client->connect());
        // Idle well past the 2s timeout; the client answers server pings, so the
        // connection must still work when we finally send.
        $loop->addTimer(3.5, static fn () => $client->send('alive'));
        $loop->addTimer(5.0, static fn () => $loop->stop());

        $server->run();

        self::assertSame('echo: alive', $handler->message);
    }

    public function testTcpIdleClientIsClosed(): void
    {
        $port = $this->freePort();
        $loop = new StreamSelectLoop();

        $server = new ReactTcpServer(ServerConfig::forTcp([
            'host' => '127.0.0.1',
            'port' => $port,
            'framing' => 'eof',
            'delimiter' => "\n",
            'heartbeat' => ['interval' => 1, 'timeout' => 2],
        ]), $loop);
        $server->setHandler(new class extends AbstractTcpServerHandler {
        });

        $handler = new class implements ClientHandlerInterface {
            public bool $closed = false;

            public function onConnect(): void
            {
            }

            public function onMessage(Message $message): void
            {
            }

            public function onClose(): void
            {
                $this->closed = true;
            }

            public function onError(\Throwable $exception): void
            {
            }
        };

        $client = new ReactTcpClient(ClientConfig::forTcp([
            'host' => '127.0.0.1',
            'port' => $port,
            'framing' => 'eof',
            'delimiter' => "\n",
        ]), $loop);
        $client->setHandler($handler);

        $loop->addTimer(0.1, static fn () => $client->connect());
        $loop->addTimer(4.0, static fn () => $loop->stop());

        $server->run();

        self::assertTrue($handler->closed);
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::markTestSkipped("Cannot allocate a port: {$errstr}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
