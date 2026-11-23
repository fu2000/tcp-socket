<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests;

use Hk100\TcpSocket\Client\ClientConfig;
use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Driver\React\ReactWebSocketClient;
use Hk100\TcpSocket\Driver\React\ReactWebSocketServer;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Tests\Support\CollectingClientHandler;
use PHPUnit\Framework\TestCase;
use React\EventLoop\StreamSelectLoop;

final class ReactWebSocketEchoTest extends TestCase
{
    public function testEchoOverWebSocket(): void
    {
        $port = $this->freePort();
        $loop = new StreamSelectLoop();

        $server = new ReactWebSocketServer(ServerConfig::forWebSocket([
            'host' => '127.0.0.1',
            'port' => $port,
            'path' => '/',
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
        $handler->onConnect = static fn () => $client->send('hi');

        $loop->addTimer(0.1, static fn () => $client->connect());
        $loop->addTimer(3.0, static fn () => $loop->stop());

        $server->run();

        self::assertSame('echo: hi', $handler->message);
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
