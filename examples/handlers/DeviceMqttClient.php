<?php

declare(strict_types=1);

namespace App\Mqtt;

use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttMessage;

/**
 * Example MQTT subscriber.
 *
 * On connect it automatically subscribes to every topic configured under
 * "tcp-socket.mqtt.subscriptions". Point "tcp-socket.mqtt.handler" at it.
 */
final class DeviceMqttClient extends AbstractMqttClient
{
    public function onMessage(MqttMessage $message): void
    {
        // e.g. $deviceId = explode('/', $message->topic)[1];
        // handle $message->payload for $deviceId
    }

    protected function onConnect(): void
    {
        // Optionally publish something once connected:
        // $this->publish('device/status', 'online', 0, true);
    }
}
