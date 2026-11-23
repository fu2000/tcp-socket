<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Hyperf;

use Hk100\TcpSocket\Client\ClientBuilder;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Hyperf\Command\MqttListenCommand;
use Hk100\TcpSocket\Hyperf\Command\TcpServerCommand;
use Hk100\TcpSocket\Hyperf\Command\WebSocketServerCommand;
use Hk100\TcpSocket\Server\ServerRunner;
use Hk100\TcpSocket\Support\ConfigRepositoryInterface;
use Hyperf\Contract\ConfigInterface;
use Psr\Container\ContainerInterface;

/**
 * Hyperf auto-discovery entry point (extra.hyperf.config in composer.json).
 */
final class ConfigProvider
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(): array
    {
        return [
            'dependencies' => [
                DriverManager::class => static function (ContainerInterface $container): DriverManager {
                    $config = $container->get(ConfigInterface::class);

                    return new DriverManager((string) $config->get('tcp-socket.driver', 'auto'));
                },
                ServerRunner::class => static function (ContainerInterface $container): ServerRunner {
                    return new ServerRunner($container->get(DriverManager::class));
                },
                ClientBuilder::class => static function (ContainerInterface $container): ClientBuilder {
                    return new ClientBuilder($container->get(DriverManager::class));
                },
                ConfigRepositoryInterface::class => static function (ContainerInterface $container): HyperfConfigRepository {
                    return new HyperfConfigRepository($container->get(ConfigInterface::class));
                },
            ],
            'commands' => [
                TcpServerCommand::class,
                WebSocketServerCommand::class,
                MqttListenCommand::class,
            ],
        ];
    }
}
