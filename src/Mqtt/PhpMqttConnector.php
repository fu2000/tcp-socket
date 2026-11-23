<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Mqtt;

use Hk100\TcpSocket\Contracts\MqttConnectorInterface;
use PhpMqtt\Client\ConnectionSettings;
use PhpMqtt\Client\MqttClient;

/**
 * MQTT transport built on php-mqtt/client (pure PHP, framework agnostic).
 */
final class PhpMqttConnector implements MqttConnectorInterface
{
    private ?MqttClient $client = null;

    /** @var callable(MqttMessage): void|null */
    private $messageHandler = null;

    public function setMessageHandler(callable $handler): void
    {
        $this->messageHandler = $handler;
    }

    public function connect(MqttConfig $config): void
    {
        $settings = (new ConnectionSettings())
            ->setConnectTimeout($config->connectTimeout)
            ->setKeepAliveInterval($config->keepAlive);

        if ($config->username !== null) {
            $settings->setUsername($config->username);
        }
        if ($config->password !== null) {
            $settings->setPassword($config->password);
        }

        $settings->setUseTls($config->tls !== null);

        if ($config->tls !== null) {
            $this->applyTlsSettings($settings, $config->tls);
        }

        $clientId = $config->clientId ?? 'hk100-' . bin2hex(random_bytes(4));
        $this->client = new MqttClient($config->host, $config->port, $clientId);
        $this->client->connect($settings, $config->cleanSession);
    }

    public function subscribe(string $topic, int $qos = 0): void
    {
        $client = $this->requireClient();
        $client->subscribe($topic, function (string $topic, string $message, bool $retained = false): void {
            if ($this->messageHandler !== null) {
                ($this->messageHandler)(new MqttMessage($topic, $message, 0, $retained));
            }
        }, $qos);
    }

    public function unsubscribe(string $topic): void
    {
        $this->requireClient()->unsubscribe($topic);
    }

    public function publish(string $topic, string $payload, int $qos = 0, bool $retain = false): void
    {
        $this->requireClient()->publish($topic, $payload, $qos, $retain);
    }

    public function loop(): void
    {
        $this->requireClient()->loop(true);
    }

    public function disconnect(): void
    {
        if ($this->client !== null && $this->client->isConnected()) {
            $this->client->disconnect();
        }
    }

    public function isConnected(): bool
    {
        return $this->client !== null && $this->client->isConnected();
    }

    private function requireClient(): MqttClient
    {
        if ($this->client === null) {
            throw new \LogicException('MQTT client is not connected yet.');
        }

        return $this->client;
    }

    /**
     * @param array<string, mixed> $tls
     */
    private function applyTlsSettings(ConnectionSettings $settings, array $tls): void
    {
        if (!method_exists($settings, 'setTlsCertificates')) {
            return;
        }

        $caFile = $tls['ca_file'] ?? null;
        $certFile = $tls['cert_file'] ?? null;
        $keyFile = $tls['key_file'] ?? null;

        if ($caFile === null && $certFile === null && $keyFile === null) {
            return;
        }

        $settings->setTlsCertificates(
            $caFile,
            $certFile,
            $keyFile,
            (bool) ($tls['verify_peer'] ?? true)
        );
    }
}
