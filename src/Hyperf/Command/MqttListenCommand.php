<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Hyperf\Command;

use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Hyperf\Command\Command;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;

final class MqttListenCommand extends Command
{
    protected $name = 'tcp-socket:mqtt-listen';

    protected $description = 'Connect to the MQTT broker and subscribe to the configured topics';

    protected $coroutine = false;

    public function handle(): int
    {
        $container = ApplicationContext::getContainer();
        $config = (array) $container->get(ConfigInterface::class)->get('tcp-socket.mqtt', []);

        $class = $config['handler'] ?? null;
        if (!is_string($class) || $class === '') {
            $this->output->writeln('<error>Configure tcp-socket.mqtt.handler with a class extending AbstractMqttClient.</error>');
            return 1;
        }

        $client = $container->get($class);
        if (!$client instanceof AbstractMqttClient) {
            $this->output->writeln("<error>Configured MQTT handler {$class} must extend AbstractMqttClient.</error>");
            return 1;
        }

        $container->get(ServerRunner::class)->runMqtt(
            $client,
            MqttConfig::fromArray($config),
            $this->output
        );

        return 0;
    }
}
