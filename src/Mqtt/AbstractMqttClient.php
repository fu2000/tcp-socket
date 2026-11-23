<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Mqtt;

use Hk100\TcpSocket\Contracts\MqttConnectorInterface;

/**
 * Base class for an MQTT subscriber. Extend it, implement {@see onMessage()},
 * and the runner will connect, auto-subscribe to the configured topics and
 * keep processing messages.
 *
 * <code>
 * class DeviceMqttClient extends AbstractMqttClient
 * {
 *     public function onMessage(MqttMessage $message): void
 *     {
 *         // handle $message->topic / $message->payload
 *     }
 * }
 * </code>
 */
abstract class AbstractMqttClient
{
    private ?MqttConfig $config = null;

    private ?MqttConnectorInterface $connector = null;

    public function configure(MqttConfig $config, ?MqttConnectorInterface $connector = null): void
    {
        $this->config = $config;
        $this->connector = $connector ?? new PhpMqttConnector();
    }

    /**
     * Connect, subscribe to every configured topic and block on the broker loop.
     */
    public function run(): void
    {
        if ($this->config === null || $this->connector === null) {
            throw new \LogicException('MQTT client is not configured; call configure() before run().');
        }

        $this->connector->setMessageHandler(function (MqttMessage $message): void {
            $this->onMessage($message);
        });

        $this->connector->connect($this->config);
        $this->onConnect();

        foreach ($this->config->subscriptions as $topic => $qos) {
            $this->connector->subscribe($topic, $qos);
            $this->onSubscribe($topic, $qos);
        }

        $this->connector->loop();

        $this->onDisconnect();
    }

    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): void
    {
        $this->requireConnector()->publish($topic, $payload, $qos, $retain);
    }

    public function subscribe(string $topic, int $qos = 0): void
    {
        $this->requireConnector()->subscribe($topic, $qos);
    }

    public function unsubscribe(string $topic): void
    {
        $this->requireConnector()->unsubscribe($topic);
    }

    public function disconnect(): void
    {
        $this->connector?->disconnect();
    }

    public function isConnected(): bool
    {
        return $this->connector?->isConnected() ?? false;
    }

    public function connector(): MqttConnectorInterface
    {
        return $this->requireConnector();
    }

    /**
     * Called for every message received on a subscribed topic.
     */
    abstract public function onMessage(MqttMessage $message): void;

    protected function onConnect(): void
    {
    }

    protected function onSubscribe(string $topic, int $qos): void
    {
    }

    protected function onDisconnect(): void
    {
    }

    private function requireConnector(): MqttConnectorInterface
    {
        if ($this->connector === null) {
            throw new \LogicException('MQTT client is not configured; call configure() first.');
        }

        return $this->connector;
    }
}
