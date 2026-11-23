<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Driver\Support;

final class SwooleSupport
{
    public static function available(): bool
    {
        return extension_loaded('swoole') || extension_loaded('openswoole');
    }

    public static function inCoroutine(): bool
    {
        if (!self::available() || !class_exists('Swoole\\Coroutine')) {
            return false;
        }

        return \Swoole\Coroutine::getCid() > 0;
    }

    /**
     * Convert the generic option array into something Swoole\Server::set accepts.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function serverOptions(array $options): array
    {
        $defaults = [
            'enable_coroutine' => true,
            'worker_num' => 1,
            'log_file' => '',
            'log_level' => 1,
        ];

        return array_merge($defaults, $options);
    }
}
