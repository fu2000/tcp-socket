<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Server;

use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Driver\DriverManager;
use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttConfig;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Wires a handler into a driver-specific server, prints the listening address
 * and blocks on the server loop. Framework-agnostic: both the Laravel and the
 * Hyperf commands delegate here.
 */
final class ServerRunner
{
    public function __construct(private readonly DriverManager $drivers)
    {
    }

    public function runTcp(
        ServerConfig $config,
        TcpServerHandlerInterface $handler,
        OutputInterface $output,
        ?string $driver = null
    ): void {
        $server = $this->drivers->tcpServer($config, $driver);
        $server->setHandler($handler);

        $output->writeln(sprintf(
            '<info>TCP server listening at %s (driver: %s, framing: %s)</info>',
            $server->getAddress(),
            $this->drivers->resolve($driver),
            $config->framingName
        ));

        $server->run();
    }

    public function runWebSocket(
        ServerConfig $config,
        WebSocketServerHandlerInterface $handler,
        OutputInterface $output,
        ?string $driver = null
    ): void {
        $server = $this->drivers->webSocketServer($config, $driver);
        $server->setHandler($handler);

        $output->writeln(sprintf(
            '<info>WebSocket server listening at %s (driver: %s)</info>',
            $server->getAddress(),
            $this->drivers->resolve($driver)
        ));

        $server->run();
    }

    public function runMqtt(AbstractMqttClient $client, MqttConfig $config, OutputInterface $output): void
    {
        $client->configure($config, $this->drivers->mqttConnector());

        $topics = $config->subscriptions === [] ? '(none)' : implode(', ', array_keys($config->subscriptions));

        $output->writeln(sprintf(
            '<info>MQTT client connecting to mqtt://%s:%d, subscribing to: %s</info>',
            $config->host,
            $config->port,
            $topics
        ));

        $client->run();
    }
}
