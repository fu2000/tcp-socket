# Laravel · WebSocket 完整指南

Laravel 10 / 11 下 **WebSocket 服务端 + 客户端** 的完整步骤。

> WebSocket 是帧消息，**不需要** `framing` / `delimiter` 配置。
> WebSocket 客户端和服务端不是同一种类：服务端继承 `AbstractWebSocketServerHandler`，客户端继承 `AbstractWebSocketClient`。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

Packagist 发布前：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.2
```

## 第 2 步：发布配置文件

```bash
php artisan vendor:publish --tag=tcp-socket-config
```

## 第 3 步：创建服务端 handler

新建 **`app/Socket/DeviceWebSocketServerHandler.php`**：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;

class DeviceWebSocketServerHandler extends AbstractWebSocketServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->send(json_encode([
            'type' => 'welcome',
            'id' => $connection->id(),
        ], JSON_UNESCAPED_UNICODE));
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        // 二进制消息时 $message->binary 为 true
        if ($message->binary) {
            $connection->send('got ' . strlen($message->payload) . ' bytes');
            return;
        }

        $data = json_decode($message->payload, true);

        if (!is_array($data)) {
            $connection->send(json_encode(['type' => 'error', 'message' => 'invalid json']));
            return;
        }

        if (isset($data['user'])) {
            $connection->setContext('user', (string) $data['user']);
        }

        $connection->send(json_encode([
            'type' => 'pong',
            'user' => $connection->getContext('user'),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE));
    }

    public function onClose(ConnectionInterface $connection): void
    {
    }

    public function onError(ConnectionInterface $connection, \Throwable $exception): void
    {
        logger()->error('ws server error: ' . $exception->getMessage());
    }
}
```

## 第 4 步：配置服务端

```php
'websocket' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9502,
        'path' => '/',
        'handler' => App\Socket\DeviceWebSocketServerHandler::class,
    ],
],
```

`path` 是握手路径：客户端必须连 `ws://host:port/path`，路径不同会返回 404。

## 第 5 步：启动服务端并测试

```bash
php artisan tcp-socket:ws-serve
```

输出：

```text
WebSocket server listening at ws://0.0.0.0:9502/ (driver: react)
```

浏览器控制台测试：

```js
const ws = new WebSocket('ws://127.0.0.1:9502/');
ws.onmessage = (e) => console.log(e.data);
ws.onopen = () => ws.send(JSON.stringify({ user: 'tom', msg: 'hello' }));
```

## 第 6 步：创建客户端

新建 **`app/Socket/AppWsClient.php`**：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractWebSocketClient;
use Hk100\TcpSocket\Message\Message;

class AppWsClient extends AbstractWebSocketClient
{
    public function onConnect(): void
    {
        // 握手成功后才可发送
        $this->send(json_encode(['user' => 'tom', 'msg' => 'hello']));
    }

    public function onMessage(Message $message): void
    {
        echo '[ws] ' . $message->payload . PHP_EOL;
    }

    public function onClose(): void
    {
        echo '[ws] closed' . PHP_EOL;
    }

    public function onError(\Throwable $exception): void
    {
        fwrite(STDERR, '[ws error] ' . $exception->getMessage() . PHP_EOL);
    }
}
```

发送二进制：`$this->send($bytes, true);`

## 第 7 步：创建运行客户端的命令

```bash
php artisan make:command RunWsClient
```

```php
<?php

namespace App\Console\Commands;

use App\Socket\AppWsClient;
use Hk100\TcpSocket\Client\ClientConfig;
use Illuminate\Console\Command;

class RunWsClient extends Command
{
    protected $signature = 'app:ws-client';

    protected $description = 'Connect to the WebSocket server';

    public function handle(): int
    {
        $client = new AppWsClient();
        $client->configure(ClientConfig::forWebSocket([
            'url' => 'ws://127.0.0.1:9502/',
        ]));

        $client->connect();   // React 驱动下是异步握手
        $client->run();       // 阻塞运行；Ctrl+C 退出

        return self::SUCCESS;
    }
}
```

运行：

```bash
php artisan app:ws-client
```

### 想交互式发送？

React 驱动下握手是异步的，`connect()` 之后立刻 `send()` 不生效。用事件循环读终端：

```php
use React\EventLoop\Loop;

public function handle(): int
{
    $client = new AppWsClient();
    $client->configure(ClientConfig::forWebSocket(['url' => 'ws://127.0.0.1:9502/']));

    $client->connect();

    Loop::get()->addReadStream(STDIN, function ($stream) use ($client): void {
        $line = trim((string) fgets($stream));
        if ($line === '') {
            return;
        }
        if ($line === 'quit') {
            Loop::get()->removeReadStream($stream);
            $client->close();
            Loop::get()->stop();
            return;
        }
        $client->send($line);
    });

    $client->run();

    return self::SUCCESS;
}
```

## 常见问题

| 现象 | 处理 |
| --- | --- |
| 握手失败 / 404 | 客户端 URL 的 path 与服务端 `websocket.server.path` 不一致 |
| 收不到消息 | 确认客户端继承 `AbstractWebSocketClient` 且用 `ClientConfig::forWebSocket` |
| `WebSocket handshake failed` | 服务端没启动、端口错误，或把 TCP 客户端连到了 WS 端口 |
| 浏览器连不上 | 使用 `ws://`，端口是 `websocket.server.port` |
| 发送返回 false | 还在异步握手，把首条发送放到 `onConnect()` |
