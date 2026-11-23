<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Laravel\Console;

use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Illuminate\Console\Command;

final class TcpServerCommand extends Command
{
    protected $signature = 'tcp-socket:serve
        {--driver= : Driver to use: auto, swoole or react}
        {--host= : Override the listen host}
        {--port= : Override the listen port}';

    protected $description = 'Start the TCP socket server';

    public function handle(ServerRunner $runner): int
    {
        $config = (array) config('tcp-socket.tcp.server', []);

        if (($host = $this->option('host')) !== null) {
            $config['host'] = $host;
        }
        if (($port = $this->option('port')) !== null) {
            $config['port'] = (int) $port;
        }

        $handler = $this->resolveHandler($config['handler'] ?? null, TcpServerHandlerInterface::class);
        if ($handler === null) {
            $this->error('Configure tcp-socket.tcp.server.handler with a class extending AbstractTcpServerHandler.');
            return self::FAILURE;
        }

        $runner->runTcp(ServerConfig::forTcp($config), $handler, $this->getOutput(), $this->option('driver'));

        return self::SUCCESS;
    }

    /**
     * @param class-string|mixed $class
     */
    private function resolveHandler(mixed $class, string $interface): ?object
    {
        if (!is_string($class) || $class === '') {
            return null;
        }

        $instance = $this->laravel->make($class);

        return $instance instanceof $interface ? $instance : null;
    }
}
