<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Support;

/**
 * Built-in default configuration. Keeps the package usable even when the host
 * application provides no config file (notably on Hyperf).
 */
final class Defaults
{
    /**
     * @return array<string, mixed>
     */
    public static function config(): array
    {
        return [
            'driver' => 'auto',
            'tcp' => [
                'server' => [
                    'host' => '0.0.0.0',
                    'port' => 9501,
                    'handler' => null,
                    'framing' => 'eof',
                    'delimiter' => "\n",
                    'length_bytes' => 4,
                    'options' => [],
                ],
                'client' => [
                    'host' => '127.0.0.1',
                    'port' => 9501,
                    'connect_timeout' => 5,
                    'handler' => null,
                    'framing' => 'eof',
                    'delimiter' => "\n",
                    'length_bytes' => 4,
                ],
            ],
            'websocket' => [
                'server' => [
                    'host' => '0.0.0.0',
                    'port' => 9502,
                    'path' => '/',
                    'handler' => null,
                    'options' => [],
                ],
                'client' => [
                    'url' => 'ws://127.0.0.1:9502',
                    'connect_timeout' => 5,
                    'handler' => null,
                ],
            ],
            'mqtt' => [
                'host' => '127.0.0.1',
                'port' => 1883,
                'client_id' => null,
                'username' => null,
                'password' => null,
                'clean_session' => true,
                'keep_alive' => 60,
                'tls' => null,
                'handler' => null,
                'subscriptions' => [],
            ],
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::config();
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Recursively merge user config over the defaults.
     *
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    public static function merge(array $config): array
    {
        return self::mergeRecursive(self::config(), $config);
    }

    /**
     * @param array<string, mixed> $base
     * @param array<string, mixed> $override
     * @return array<string, mixed>
     */
    private static function mergeRecursive(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }
}
