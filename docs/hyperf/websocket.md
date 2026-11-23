# Hyperf · WebSocket 完整指南

Hyperf 3.x 下 **WebSocket 服务端 + 客户端** 的完整步骤。

> WebSocket 是帧消息，**不需要** TCP 的 `framing` / `delimiter` 配置。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

Packagist 发布前：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.2
```

## 第 2 步：创建配置文件（必须手动）

```bash
cp vendor/hk100/tcp-socket/config/tcp-socket.php config/autoload/tcp-socket.php
```

## 第 3 步：创建服务端 handler

新建 **`app/Socket/DeviceWebSocketServerHandler.php`**：

```php
<?php

declare(strict_types=1);

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

        // 保存业务状态到当前连接
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

编辑 **`config/autoload/tcp-socket.php`**：

```php
'websocket' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9502,
        'path' => '/',
        'handler' => App\Socket\DeviceWebSocketServerHandler::class,
        'options' => [
            'log_file' => BASE_PATH . '/runtime/logs/swoole.log',
            'log_level' => 4,
        ],
    ],
],
```

`path` 是握手路径：客户端必须连 `ws://host:port/path`，路径不一致会返回 404。

## 第 5 步：启动服务端并测试

```bash
php bin/hyperf.php tcp-socket:ws-serve
```

输出：

```text
WebSocket server listening at ws://0.0.0.0:9502/ (driver: swoole)
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

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractWebSocketClient;
use Hk100\TcpSocket\Message\Message;

class AppWsClient extends AbstractWebSocketClient
{
    public function onConnect(): void
    {
        // 握手成功后才能发送
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

新建 **`app/Command/RunWsClientCommand.php`**：

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Socket\AppWsClient;
use Hk100\TcpSocket\Client\ClientConfig;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command]
class RunWsClientCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('app:ws-client');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('连接 WebSocket 服务端并收发消息');
    }

    public function handle()
    {
        $client = new AppWsClient();
        $client->configure(ClientConfig::forWebSocket([
            'url' => 'ws://127.0.0.1:9502/',
        ]));

        // Hyperf 命令在协程中，auto 选 swoole 客户端，握手是同步的
        $client->connect();

        // 握手完成后随时可发：从终端逐行读取
        while (($line = fgets(STDIN)) !== false) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if ($line === 'quit') {
                break;
            }
            $client->send($line);
        }

        $client->close();

        return 0;
    }
}
```

## 第 8 步：运行客户端并收发

```bash
# 终端 A：启动 WebSocket 服务端
php bin/hyperf.php tcp-socket:ws-serve

# 终端 B：运行客户端
php bin/hyperf.php app:ws-client
```

预期输出：

```text
[ws] {"type":"welcome","id":1}
[ws] {"type":"pong","user":"tom","data":{"user":"tom","msg":"hello"}}
```

之后在终端输入任意内容回车发送，输入 `quit` 退出。

## 常见问题

| 现象 | 处理 |
| --- | --- |
| 握手失败 / 404 | 客户端 URL 的 `path` 与服务端 `websocket.server.path` 不一致 |
| `WebSocket handshake failed` | 服务端没启动、端口错误，或用 TCP 客户端连了 WS 端口 |
| 收不到消息 | 确认用的是 `AbstractWebSocketClient` + `ClientConfig::forWebSocket` |
| 提示 Handler class does not exist | 第 3 步的类没建或命名空间写错 |
| 浏览器连不上 | 确认连的是 `ws://`，端口是 `websocket.server.port` |
