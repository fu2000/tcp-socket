<?php

declare(strict_types=1);

/*
 * Example Hyperf config. Copy this file to config/autoload/tcp-socket.php.
 *
 * Start the servers with:
 *   php bin/hyperf.php tcp-socket:serve
 *   php bin/hyperf.php tcp-socket:ws-serve
 *   php bin/hyperf.php tcp-socket:mqtt-listen
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
