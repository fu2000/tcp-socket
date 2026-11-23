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

## 收到消息后怎么处理（服务端）

真实业务一般是：解析 JSON → 按 `type` 分发 → 调业务类 → 回复浏览器/设备。handler 由容器解析，可以构造函数注入业务类：

```php
<?php

declare(strict_types=1);

namespace App\Socket;

use App\Service\DeviceService;
use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;

class DeviceWebSocketServerHandler extends AbstractWebSocketServerHandler
{
    public function __construct(private readonly DeviceService $devices)
    {
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        if ($message->binary) {
            // 二进制帧：图片、音频、自定义协议头等
            $connection->send('got ' . strlen($message->payload) . ' bytes');
            return;
        }

        $data = json_decode($message->payload, true);

        if (!is_array($data)) {
            $connection->send($this->reply('error', ['message' => 'invalid json']));
            return;
        }

        switch ($data['type'] ?? '') {
            case 'register':
                $connection->setContext('user', (string) ($data['user'] ?? ''));
                $connection->send($this->reply('registered', [
                    'user' => $connection->getContext('user'),
                ]));
                break;

            case 'join':
                // 订阅某个房间/主题：自己维护“房间 => 连接集合”即可
                $connection->setContext('room', (string) ($data['room'] ?? 'default'));
                $connection->send($this->reply('joined', ['room' => $connection->getContext('room')]));
                break;

            case 'chat':
                // 业务处理：入库、过滤、转发给同房间的其他连接
                $this->devices->storeChat(
                    (string) $connection->getContext('user'),
                    (string) ($data['message'] ?? '')
                );
                $connection->send($this->reply('chat_ack', ['ok' => true]));
                break;

            case 'heartbeat':
                // 应用层心跳（可选；协议层 ping/pong 已由包自动处理）
                $connection->send($this->reply('pong', ['ts' => time()]));
                break;

            default:
                $connection->send($this->reply('error', ['message' => 'unknown type']));
        }
    }

    private function reply(string $type, array $payload = []): string
    {
        return json_encode(['type' => $type] + $payload, JSON_UNESCAPED_UNICODE);
    }
}
```

> 服务端主动推送（例如“有新消息了”）就在业务代码里拿保存好的 `ConnectionInterface` 调 `$connection->send($json)`；
> 需要“广播”时，自己在 `onOpen`/`onMessage` 里维护连接列表，遍历 `send()`。

## 客户端收到服务端推送后怎么处理

连接成功后，服务端可能随时推送消息（通知、指令、数据变更）。在客户端的 `onMessage` 里按 `type` 执行对应逻辑：

```php
public function onMessage(Message $message): void
{
    $data = json_decode($message->payload, true);

    if (!is_array($data)) {
        echo '[ws] ' . $message->payload . PHP_EOL;
        return;
    }

    switch ($data['type'] ?? '') {
        case 'welcome':
            echo "[ws] connected as #{$data['id']}\n";
            break;

        case 'command':
            // 服务端下发的指令：执行后把结果发回去
            $result = $this->executeCommand($data);
            $this->send(json_encode([
                'type' => 'command_result',
                'id' => $data['id'] ?? null,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE));
            break;

        case 'notice':
            // 普通通知：写日志、写库、推给业务系统
            logger()->info('ws notice', $data);
            break;

        case 'pong':
            break;

        default:
            echo '[ws] ' . $message->payload . PHP_EOL;
    }
}

private function executeCommand(array $command): array
{
    return match ($command['action'] ?? '') {
        'reboot' => ['ok' => true, 'action' => 'reboot'],
        'collect' => ['ok' => true, 'temperature' => 23.5],
        default => ['ok' => false, 'message' => 'unknown action'],
    };
}
```

## 保活（心跳）

WebSocket 有协议层 ping/pong，本包在服务端和客户端都已自动处理：

- 收到 ping 帧 → 自动回 pong（不会进 `onMessage`）；
- 服务端配置 `heartbeat` 后，会定时向客户端发 ping，`timeout` 内没收到 pong 就断开该连接。

服务端配置（`config/autoload/tcp-socket.php`）：

```php
'websocket' => [
    'server' => [
        // ...
        'heartbeat' => [
            'interval' => 30,   // 每 30 秒发一次 ping
            'timeout' => 90,    // 90 秒内没有 pong 就断开
        ],
    ],
],
```

- **swoole 驱动**：开启 `open_websocket_ping_frame`，由定时器每 `interval` 秒发 ping 帧，
  并按 Swoole 记录的最后活跃时间判断 `timeout`，超时的连接直接断开。
- **react 驱动**：服务端自己每 `interval` 秒发 ping 帧并清理超时连接。

**设备端/浏览器端不需要写保活代码**：本包的 `AbstractWebSocketClient`、浏览器原生 `WebSocket` 都会自动回 pong。
如果你的业务还需要应用层心跳（例如浏览器页面自己发 `{"type":"heartbeat"}`），按上面的 `case 'heartbeat'` 分支处理即可。

## 常见问题

| 现象 | 处理 |
| --- | --- |
| 握手失败 / 404 | 客户端 URL 的 `path` 与服务端 `websocket.server.path` 不一致 |
| `WebSocket handshake failed` | 服务端没启动、端口错误，或用 TCP 客户端连了 WS 端口 |
| 收不到消息 | 确认用的是 `AbstractWebSocketClient` + `ClientConfig::forWebSocket` |
| 提示 Handler class does not exist | 第 3 步的类没建或命名空间写错 |
| 浏览器连不上 | 确认连的是 `ws://`，端口是 `websocket.server.port` |
