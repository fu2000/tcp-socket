<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Contracts;

use Hk100\TcpSocket\Mqtt\MqttConfig;
use Hk100\TcpSocket\Mqtt\MqttMessage;

/**
 * An MQTT transport (connect / subscribe / publish / loop).
 */
interface MqttConnectorInterface
{
    /**
     * @param callable(MqttMessage): void $handler
     */
    public function setMessageHandler(callable $handler): void;

    public function connect(MqttConfig $config): void;

    public function subscribe(string $topic, int $qos = 0): void;

    public function unsubscribe(string $topic): void;

    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): void;

    /**
     * Block and process incoming messages until disconnected.
     */
    public function loop(): void;

    public function disconnect(): void;

    public function isConnected(): bool;
}
