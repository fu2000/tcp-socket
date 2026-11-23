<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Laravel;

use Hk100\TcpSocket\Client\ClientBuilder;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Laravel\Console\MqttListenCommand;
use Hk100\TcpSocket\Laravel\Console\TcpServerCommand;
use Hk100\TcpSocket\Laravel\Console\WebSocketServerCommand;
use Hk100\TcpSocket\Server\ServerRunner;
use Hk100\TcpSocket\Support\ConfigRepositoryInterface;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class TcpSocketServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(self::configPath(), 'tcp-socket');

        $this->app->singleton(ConfigRepositoryInterface::class, function (Application $app): LaravelConfigRepository {
            return new LaravelConfigRepository($app['config']);
        });

        $this->app->singleton(DriverManager::class, function (Application $app): DriverManager {
            return new DriverManager((string) $app['config']->get('tcp-socket.driver', 'auto'));
        });

        $this->app->singleton(ServerRunner::class, function (Application $app): ServerRunner {
            return new ServerRunner($app->make(DriverManager::class));
        });

        $this->app->singleton(ClientBuilder::class, function (Application $app): ClientBuilder {
            return new ClientBuilder($app->make(DriverManager::class));
        });
    }

    public function boot(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            self::configPath() => $this->app->configPath('tcp-socket.php'),
        ], 'tcp-socket-config');

        $this->commands([
            TcpServerCommand::class,
            WebSocketServerCommand::class,
            MqttListenCommand::class,
        ]);
    }

    private static function configPath(): string
    {
        return dirname(__DIR__, 2) . '/config/tcp-socket.php';
    }
}
