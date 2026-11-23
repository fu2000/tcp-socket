<?php

declare(strict_types=1);

/*
 * Example Laravel config. After:
 *   php artisan vendor:publish --tag=tcp-socket-config
 * edit config/tcp-socket.php to look like this.
 */

return [
    'driver' => 'auto',

    'tcp' => [
        'server' => [
            'host' => '0.0.0.0',
            'port' => 9501,
            'handler' => App\Socket\EchoTcpServerHandler::class,
            'framing' => 'eof',
            'delimiter' => "\n",
            'heartbeat' => ['interval' => 30, 'timeout' => 90],
        ],
        'client' => [
            'host' => '127.0.0.1',
            'port' => 9501,
        ],
    ],

    'websocket' => [
        'server' => [
            'host' => '0.0.0.0',
            'port' => 9502,
            'path' => '/',
            'handler' => App\Socket\EchoWebSocketServerHandler::class,
            'heartbeat' => ['interval' => 30, 'timeout' => 90],
        ],
        'client' => [
            'url' => 'ws://127.0.0.1:9502',
        ],
    ],

    'mqtt' => [
        'host' => '127.0.0.1',
        'port' => 1883,
        'handler' => App\Mqtt\DeviceMqttClient::class,
        'subscriptions' => [
            'device/+/data' => 0,
        ],
    ],
];
