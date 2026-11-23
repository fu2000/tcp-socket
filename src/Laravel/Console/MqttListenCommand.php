<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Laravel\Console;

use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Illuminate\Console\Command;

final class MqttListenCommand extends Command
{
    protected $signature = 'tcp-socket:mqtt-listen';

    protected $description = 'Connect to the MQTT broker and subscribe to the configured topics';

    public function handle(ServerRunner $runner): int
    {
        $config = (array) config('tcp-socket.mqtt', []);
        $class = $config['handler'] ?? null;

        if (!is_string($class) || $class === '') {
            $this->error('Configure tcp-socket.mqtt.handler with a class extending AbstractMqttClient.');
            return self::FAILURE;
        }
        if (!class_exists($class)) {
            $this->error("MQTT handler class {$class} does not exist. Check tcp-socket.mqtt.handler in config/tcp-socket.php.");
            return self::FAILURE;
        }

        $client = $this->laravel->make($class);
        if (!$client instanceof AbstractMqttClient) {
            $this->error("Configured MQTT handler {$class} must extend AbstractMqttClient.");
            return self::FAILURE;
        }

        $runner->runMqtt($client, MqttConfig::fromArray($config), $this->getOutput());

        return self::SUCCESS;
    }
}
