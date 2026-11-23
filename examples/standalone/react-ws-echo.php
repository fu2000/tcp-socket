<?php

declare(strict_types=1);

/*
 * Standalone ReactPHP WebSocket echo server:
 *
 *   php examples/standalone/react-ws-echo.php 0.0.0.0 9502
 *   websocat ws://127.0.0.1:9502
 */

require __DIR__ . '/../../vendor/autoload.php';

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;
use Hk100\TcpSocket\Server\ServerConfig;

$host = $argv[1] ?? '0.0.0.0';
$port = (int) ($argv[2] ?? 9502);

$handler = new class extends AbstractWebSocketServerHandler {
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->send('connected');
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $connection->send('echo: ' . $message->payload);
    }
};

$config = ServerConfig::forWebSocket([
    'host' => $host,
    'port' => $port,
    'path' => '/',
]);

$server = (new DriverManager('react'))->webSocketServer($config);
$server->setHandler($handler);

fwrite(STDOUT, "WebSocket echo server (react) listening at {$server->getAddress()}\n");
$server->run();
