<?php

declare(strict_types=1);

/*
 * Standalone Swoole TCP echo server (requires ext-swoole). Plain CLI is a
 * non-coroutine process, so Swoole\Server can be created directly:
 *
 *   php examples/standalone/swoole-tcp-echo.php 0.0.0.0 9501
 *   printf 'hello\n' | nc 127.0.0.1 9501
 */

require __DIR__ . '/../../vendor/autoload.php';

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;
use Hk100\TcpSocket\Server\ServerConfig;

$host = $argv[1] ?? '0.0.0.0';
$port = (int) ($argv[2] ?? 9501);

$handler = new class extends AbstractTcpServerHandler {
    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $connection->send('echo: ' . $message->payload);
    }
};

$config = ServerConfig::forTcp([
    'host' => $host,
    'port' => $port,
    'framing' => 'eof',
    'delimiter' => "\n",
]);

$server = (new DriverManager('swoole'))->tcpServer($config);
$server->setHandler($handler);

fwrite(STDOUT, "TCP echo server (swoole) listening at {$server->getAddress()}\n");
$server->run();
