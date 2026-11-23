<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Mqtt;

/**
 * An application message received from an MQTT broker.
 */
final class MqttMessage
{
    public function __construct(
        public readonly string $topic,
        public readonly string $payload,
        public readonly int $qos = 0,
        public readonly bool $retain = false,
        public readonly bool $duplicate = false,
        public readonly ?int $messageId = null,
    ) {
    }
}
