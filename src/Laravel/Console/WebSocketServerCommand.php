<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Laravel\Console;

use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Illuminate\Console\Command;

final class WebSocketServerCommand extends Command
{
    protected $signature = 'tcp-socket:ws-serve
        {--driver= : Driver to use: auto, swoole or react}
        {--host= : Override the listen host}
        {--port= : Override the listen port}';

    protected $description = 'Start the WebSocket server';

    public function handle(ServerRunner $runner): int
    {
        $config = (array) config('tcp-socket.websocket.server', []);

        if (($host = $this->option('host')) !== null) {
            $config['host'] = $host;
        }
        if (($port = $this->option('port')) !== null) {
            $config['port'] = (int) $port;
        }

        $handler = $this->resolveHandler($config['handler'] ?? null);
        if ($handler === null) {
            $this->error('Configure tcp-socket.websocket.server.handler with a class extending AbstractWebSocketServerHandler.');
            return self::FAILURE;
        }

        $runner->runWebSocket(ServerConfig::forWebSocket($config), $handler, $this->getOutput(), $this->option('driver'));

        return self::SUCCESS;
    }

    private function resolveHandler(mixed $class): ?object
    {
        if (!is_string($class) || $class === '' || !class_exists($class)) {
            return null;
        }

        $instance = $this->laravel->make($class);

        return $instance instanceof WebSocketServerHandlerInterface ? $instance : null;
    }
}
