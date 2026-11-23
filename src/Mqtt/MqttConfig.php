<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Mqtt;

/**
 * Normalised MQTT broker settings.
 */
final class MqttConfig
{
    /**
     * @param array<string, mixed>  $tls           TLS options; null disables TLS.
     * @param array<string, int>    $subscriptions Topic filter => QoS, subscribed automatically on connect.
     */
    public function __construct(
        public readonly string $host,
        public readonly int $port = 1883,
        public readonly ?string $clientId = null,
        public readonly ?string $username = null,
        public readonly ?string $password = null,
        public readonly bool $cleanSession = true,
        public readonly int $keepAlive = 60,
        public readonly int $connectTimeout = 5,
        public readonly ?array $tls = null,
        public readonly array $subscriptions = [],
    ) {
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        $subscriptions = [];
        foreach ((array) ($config['subscriptions'] ?? []) as $topic => $qos) {
            $subscriptions[(string) $topic] = (int) $qos;
        }

        return new self(
            host: (string) ($config['host'] ?? '127.0.0.1'),
            port: (int) ($config['port'] ?? 1883),
            clientId: isset($config['client_id']) ? (string) $config['client_id'] : null,
            username: isset($config['username']) ? (string) $config['username'] : null,
            password: isset($config['password']) ? (string) $config['password'] : null,
            cleanSession: (bool) ($config['clean_session'] ?? true),
            keepAlive: (int) ($config['keep_alive'] ?? 60),
            connectTimeout: (int) ($config['connect_timeout'] ?? 5),
            tls: isset($config['tls']) && is_array($config['tls']) ? $config['tls'] : null,
            subscriptions: $subscriptions,
        );
    }
}
