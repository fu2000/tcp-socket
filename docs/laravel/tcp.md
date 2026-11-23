# Laravel · TCP Socket 完整指南

Laravel 10 / 11 下 **TCP 服务端 + 客户端** 的完整步骤。

> Laravel 的命令/请求不是协程环境，`auto` 驱动默认使用 `react`（纯 PHP），不装 Swoole 也能跑。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

Packagist 发布前：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.2
```

Laravel 会自动发现 ServiceProvider（`extra.laravel.providers`）。

检查命令：

```bash
php artisan list | grep tcp-socket
```

## 第 2 步：发布配置文件

```bash
php artisan vendor:publish --tag=tcp-socket-config
```

生成 **`config/tcp-socket.php`**，后续都改这个文件。

## 第 3 步：创建服务端 handler

新建 **`app/Socket/DeviceTcpServerHandler.php`**：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpServerHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->send("welcome, you are connection #{$connection->id()}");
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        // 收到的是“一条完整消息”（分帧已由底层处理）
        $data = json_decode($message->payload, true);

        if (!is_array($data)) {
            $connection->send('invalid json');
            return;
        }

        if (isset($data['device_id'])) {
            $connection->setContext('device_id', (string) $data['device_id']);
        }

        $connection->send(json_encode([
            'ack' => true,
            'device_id' => $connection->getContext('device_id'),
            'echo' => $data,
        ], JSON_UNESCAPED_UNICODE));
    }

    public function onClose(ConnectionInterface $connection): void
    {
    }

    public function onError(ConnectionInterface $connection, \Throwable $exception): void
    {
        logger()->error('tcp server error: ' . $exception->getMessage());
    }
}
```

## 第 4 步：配置服务端

```php
'tcp' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'handler' => App\Socket\DeviceTcpServerHandler::class,
        'framing' => 'eof',
        'delimiter' => "\n",
    ],
],
```

## 第 5 步：启动服务端并测试

```bash
php artisan tcp-socket:serve
```

输出：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: react, framing: eof)
```

测试（`eof` 分帧必须带换行）：

```bash
printf '{"device_id":"dev-1"}\n' | nc 127.0.0.1 9501
```

没有 `nc`：

```bash
php -r '$f=stream_socket_client("tcp://127.0.0.1:9501"); stream_set_timeout($f,3);
echo fgets($f), "\n"; fwrite($f, "{\"device_id\":\"dev-1\"}\n"); echo fgets($f), "\n";'
```

常用参数：

```bash
php artisan tcp-socket:serve --port=9511
php artisan tcp-socket:serve --driver=swoole   # 需要 ext-swoole
```

## 第 6 步：创建客户端

新建 **`app/Socket/AppTcpClient.php`**：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractTcpClient;
use Hk100\TcpSocket\Message\Message;

class AppTcpClient extends AbstractTcpClient
{
    public function onConnect(): void
    {
        // React 驱动 connect() 是异步的，第一条消息放这里最稳
        $this->send(json_encode(['device_id' => 'dev-1', 'hello' => true]));
    }

    public function onMessage(Message $message): void
    {
        echo '[tcp] ' . $message->payload . PHP_EOL;
    }

    public function onClose(): void
    {
        echo '[tcp] closed' . PHP_EOL;
    }

    public function onError(\Throwable $exception): void
    {
        fwrite(STDERR, '[tcp error] ' . $exception->getMessage() . PHP_EOL);
    }
}
```

## 第 7 步：创建运行客户端的命令

客户端是长连接，用常驻 Artisan 命令运行：

```bash
php artisan make:command RunTcpClient
```

```php
<?php

namespace App\Console\Commands;

use App\Socket\AppTcpClient;
use Hk100\TcpSocket\Client\ClientConfig;
use Illuminate\Console\Command;

class RunTcpClient extends Command
{
    protected $signature = 'app:tcp-client';

    protected $description = 'Connect to the TCP server';

    public function handle(): int
    {
        $client = new AppTcpClient();
        $client->configure(ClientConfig::forTcp([
            'host' => '127.0.0.1',
            'port' => 9501,
            'framing' => 'eof',
            'delimiter' => "\n",
        ]));

        $client->connect();
        $client->run();   // 阻塞运行；Ctrl+C 退出

        return self::SUCCESS;
    }
}
```

运行：

```bash
php artisan app:tcp-client
```

### 想手动/定时发消息？

React 驱动下 `connect()` 是异步的，`connect()` 之后立刻 `send()` 会失败。要交互式发送，可以用 React 事件循环读终端：

```php
use React\EventLoop\Loop;

public function handle(): int
{
    $client = new AppTcpClient();
    $client->configure(ClientConfig::forTcp([
        'host' => '127.0.0.1', 'port' => 9501,
        'framing' => 'eof', 'delimiter' => "\n",
    ]));

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

    $client->run();   // 打开事件循环

    return self::SUCCESS;
}
```

定时发送也可以：`Loop::get()->addPeriodicTimer(3, fn () => $client->send('ping'));`

## 收到消息后怎么处理（服务端）

`onMessage` 收到的是一条完整消息。真实业务一般是：解析 JSON → 按 `type` / `cmd` 分发 → 调业务类 → 回复设备。handler 由 Laravel 容器解析（`$this->laravel->make($class)`），可以构造函数注入业务类：

```php
<?php

namespace App\Socket;

use App\Service\DeviceService;
use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpServerHandler extends AbstractTcpServerHandler
{
    public function __construct(private readonly DeviceService $devices)
    {
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $data = json_decode($message->payload, true);

        if (!is_array($data)) {
            $connection->send($this->reply('error', ['message' => 'invalid json']));
            return;
        }

        // 设备协议通常有一个 type / cmd 字段，用它分发业务
        switch ($data['type'] ?? $data['cmd'] ?? '') {
            case 'register':
                $connection->setContext('device_id', (string) ($data['device_id'] ?? ''));
                $connection->send($this->reply('registered', [
                    'device_id' => $connection->getContext('device_id'),
                ]));
                break;

            case 'heartbeat':
                $connection->setContext('last_seen', time());
                $connection->send($this->reply('pong', ['ts' => time()]));
                break;

            case 'data':
                // 业务处理：入库 / 投队列 / 调用服务
                $deviceId = (string) $connection->getContext('device_id');
                $this->devices->store($deviceId, $data);
                $connection->send($this->reply('ack', ['ok' => true]));
                break;

            case 'command_result':
                // 设备回报某条下行指令的执行结果
                $this->devices->saveCommandResult($data);
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

业务类放在任意位置（例如 `app/Services/DeviceService.php`），依赖注入即可：

```php
<?php

namespace App\Services;

class DeviceService
{
    public function store(string $deviceId, array $data): void
    {
        logger()->info('device data', ['device_id' => $deviceId, 'data' => $data]);
        // DB::table('device_data')->insert(...);
    }

    public function saveCommandResult(array $data): void
    {
        logger()->info('command result', $data);
    }
}
```

向**单个设备**下发指令：把 `ConnectionInterface` 保存起来（例如按设备号存到 Redis / 应用内数组），业务代码里 `$connection->send($json)` 即可。

## 客户端收到服务端推送后怎么处理

客户端不是只能打印。服务端下发指令时，在 `onMessage` 里解析并执行，然后把结果发回去：

```php
public function onMessage(Message $message): void
{
    $data = json_decode($message->payload, true);

    if (!is_array($data)) {
        echo '[tcp] ' . $message->payload . PHP_EOL;
        return;
    }

    switch ($data['type'] ?? '') {
        case 'command':
            // 执行服务端指令（重启、采集、升级……），再回报结果
            $result = $this->executeCommand($data);
            $this->send(json_encode([
                'type' => 'command_result',
                'id' => $data['id'] ?? null,
                'result' => $result,
            ], JSON_UNESCAPED_UNICODE));
            break;

        case 'pong':
            // 心跳响应
            break;

        default:
            echo '[tcp] ' . $message->payload . PHP_EOL;
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

| 协议 | 心跳机制 | 需要你写代码吗 |
| --- | --- | --- |
| WebSocket | 协议层 ping/pong：服务端定时发 ping，客户端**自动回 pong**（包内已实现） | 不需要 |
| TCP | 协议本身没有 ping/pong | 设备端定时发应用层心跳（例如 `{"type":"heartbeat"}`） |
| MQTT | `keep_alive` 由 broker 协议保活 | 不需要（见 MQTT 指南） |

### 服务端：自动断开“死连接”

给服务端加 `heartbeat` 配置即可（`config/tcp-socket.php`）：

```php
'tcp' => [
    'server' => [
        // ...
        'heartbeat' => [
            'interval' => 30,   // 检测间隔（秒）
            'timeout' => 90,    // 超过多少秒没有任何数据就断开
        ],
    ],
],
```

- **react 驱动（Laravel 默认）**：TCP 服务端没有协议层 ping，只做“空闲超过 `timeout` 秒就断开”，
  因此设备端要按下面的示例定时发心跳。
- **swoole 驱动**：自动映射成 Swoole 的 `heartbeat_check_interval` / `heartbeat_idle_time`。
- 不配置 `heartbeat`（或设为 `null`）就关闭该功能。

### 设备端：定时发心跳（TCP）

React 驱动下用事件循环的定时器。在客户端 `onConnect()` 里启动，`run()` 后循环持续运行：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractTcpClient;
use Hk100\TcpSocket\Message\Message;
use React\EventLoop\Loop;

class AppTcpClient extends AbstractTcpClient
{
    public function onConnect(): void
    {
        $this->send(json_encode(['type' => 'register', 'device_id' => 'dev-1']));

        // 每 30 秒发一次心跳；服务端 handler 收到 heartbeat 回 pong
        Loop::get()->addPeriodicTimer(30, function (): void {
            $this->send(json_encode(['type' => 'heartbeat', 'ts' => time()]));
        });
    }

    public function onMessage(Message $message): void
    {
        // ... 见上文“客户端收到服务端推送后怎么处理”
    }
}
```

服务端 `onMessage` 里对应的分支见上文（`case 'heartbeat'` 回复 `pong`）。这样即使设备长时间没有业务数据，连接也不会被服务端的心跳检查断开。

### 设备端：WebSocket

如果服务端开启了 `heartbeat`，它会定时发 WebSocket ping 帧；本包的 `AbstractWebSocketClient`（以及浏览器）会自动回 pong，**设备端不需要写任何保活代码**。

## 常见问题

| 现象 | 处理 |
| --- | --- |
| `TCP connection failed` / `Connection refused` | 服务端没启动，或端口/地址不对 |
| `Address already in use` | 端口被占用，加 `--port=` 换端口 |
| 找不到 `tcp-socket:*` 命令 | `php artisan optimize:clear` |
| 提示 Configure `...handler...` | `config/tcp-socket.php` 里 `handler` 为 `null` 或类名错 |
| 消息粘包 / 收不到 | `framing` 用 `eof`/`length`，服务端与客户端要一致 |
| 发送返回 false | 还没连上（React 异步），把首条发送放到 `onConnect()` |
