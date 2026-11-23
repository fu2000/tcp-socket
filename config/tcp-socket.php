<?php

declare(strict_types=1);

/*
 * Default configuration for hk100/tcp-socket.
 *
 * Laravel: publish this file with `php artisan vendor:publish --tag=tcp-socket-config`.
 * Hyperf : copy it to `config/autoload/tcp-socket.php`.
 *
 * Only the values you change need to be present; everything else falls back to
 * the built-in defaults in Hk100\TcpSocket\Support\Defaults.
 */

return [
    // auto | swoole | react
    'driver' => 'auto',

    'tcp' => [
        'server' => [
            'host' => '0.0.0.0',
            'port' => 9501,
            // Class extending Hk100\TcpSocket\Server\AbstractTcpServerHandler.
            'handler' => null,
            // raw | eof | length
            'framing' => 'eof',
            'delimiter' => "\n",
            'length_bytes' => 4,
            // Optional heartbeat. Enable by adding the array:
            // 'heartbeat' => ['interval' => 30, 'timeout' => 90],
            // Swoole maps it to heartbeat_check_interval/heartbeat_idle_time;
            // the react driver closes connections idle for longer than "timeout".
            // TCP has no protocol-level ping, so devices should send app-level
            // heartbeats (e.g. {"type":"ping"}) within "timeout".
            'heartbeat' => null,
            // Passed through to Swoole\Server::set() when using the swoole driver.
            'options' => [],
        ],
        'client' => [
            'host' => '127.0.0.1',
            'port' => 9501,
            'connect_timeout' => 5,
            // Class extending Hk100\TcpSocket\Client\AbstractTcpClient.
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
            // Class extending Hk100\TcpSocket\Server\AbstractWebSocketServerHandler.
            'handler' => null,
            // Optional heartbeat. Enable by adding the array:
            // 'heartbeat' => ['interval' => 30, 'timeout' => 90],
            // The server sends WebSocket ping frames every "interval" seconds and
            // closes clients that do not answer within "timeout" seconds. Clients
            // (including this package's WS clients and browsers) answer pings
            // automatically, so no device-side code is needed.
            'heartbeat' => null,
            'options' => [],
        ],
        'client' => [
            'url' => 'ws://127.0.0.1:9502',
            'connect_timeout' => 5,
            // Class extending Hk100\TcpSocket\Client\AbstractWebSocketClient.
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
        'connect_timeout' => 5,
        // null disables TLS; to enable, provide TLS options, e.g.
        // ['ca_file' => '/path/ca.crt', 'verify_peer' => true]
        'tls' => null,
        // Class extending Hk100\TcpSocket\Mqtt\AbstractMqttClient.
        'handler' => null,
        // Topic filter => QoS, subscribed automatically on connect.
        'subscriptions' => [
            // 'device/+/data' => 0,
        ],
    ],
];
