# Hyperf · TCP Socket 完整指南

Hyperf 3.x 下 **TCP 服务端 + 客户端** 的完整步骤：安装 → 建文件 → 配置 → 启动 → 收发消息。

> 命令统一用 `php bin/hyperf.php ...`（项目里的 `artisan` 包装脚本最终也是执行它）。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

Packagist 发布前用 VCS 方式：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.2
```

确认命令已注册：

```bash
php bin/hyperf.php list | grep tcp-socket
```

## 第 2 步：创建配置文件（必须手动）

Hyperf 不会自动生成配置文件：

```bash
cp vendor/hk100/tcp-socket/config/tcp-socket.php config/autoload/tcp-socket.php
```

包内模板位置：

- `vendor/hk100/tcp-socket/config/tcp-socket.php`：默认配置，`handler` 为 `null`
- `vendor/hk100/tcp-socket/examples/config/hyperf-tcp-socket.php`：带示例类名的配置

## 第 3 步：创建服务端 handler

新建 **`app/Socket/DeviceTcpServerHandler.php`**：

```php
<?php

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpServerHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        // 设备接入
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

        // 把设备号存进连接上下文，后续消息可以直接取
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
        // 连接断开，可清理缓存/上报离线
    }

    public function onError(ConnectionInterface $connection, \Throwable $exception): void
    {
        logger()->error('tcp server error: ' . $exception->getMessage());
    }
}
```

回调说明：

| 回调 | 触发时机 | 能做什么 |
| --- | --- | --- |
| `onOpen` | 连接建立 | 发欢迎消息、`setContext` 初始化状态 |
| `onMessage` | 收到一条完整消息 | 业务处理、`send()` 回复、读写上下文 |
| `onClose` | 连接断开 | 清理、统计 |
| `onError` | 处理/收发异常 | 记日志 |

## 第 4 步：配置服务端

编辑 **`config/autoload/tcp-socket.php`**：

```php
'tcp' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'handler' => App\Socket\DeviceTcpServerHandler::class,
        'framing' => 'eof',
        'delimiter' => "\n",
        'options' => [
            // 可选：Swoole 日志文件，避免 open() failed 提示
            'log_file' => BASE_PATH . '/runtime/logs/swoole.log',
            'log_level' => 4,
        ],
    ],
],
```

## 第 5 步：启动服务端并测试

```bash
php bin/hyperf.php tcp-socket:serve
```

输出：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: swoole, framing: eof)
```

另开终端测试（`eof` 分帧必须带换行）：

```bash
printf '{"device_id":"dev-1"}\n' | nc 127.0.0.1 9501
```

没有 `nc` 时：

```bash
php -r '$f=stream_socket_client("tcp://127.0.0.1:9501"); stream_set_timeout($f,3);
echo fgets($f), "\n"; fwrite($f, "{\"device_id\":\"dev-1\"}\n"); echo fgets($f), "\n";'
```

常用参数：

```bash
php bin/hyperf.php tcp-socket:serve --port=9511
php bin/hyperf.php tcp-socket:serve --driver=react
```

## 第 6 步：创建客户端（不使用服务端时）

客户端用于**主动连接别人的 TCP 服务器**。

新建 **`app/Socket/AppTcpClient.php`**：

```php
<?php

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractTcpClient;
use Hk100\TcpSocket\Message\Message;

class AppTcpClient extends AbstractTcpClient
{
    public function onConnect(): void
    {
        // 连接成功后发送；不要手动加 "\n"，eof 分帧会自动补
        $this->send(json_encode(['device_id' => 'dev-1', 'hello' => true]));
    }

    public function onMessage(Message $message): void
    {
        // 收到服务端消息：直接打印，或写日志/入库
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

新建 **`app/Command/RunTcpClientCommand.php`**：

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Socket\AppTcpClient;
use Hk100\TcpSocket\Client\ClientConfig;
use Hyperf\Command\Annotation\Command;
use Hyperf\Command\Command as HyperfCommand;

#[Command]
class RunTcpClientCommand extends HyperfCommand
{
    public function __construct()
    {
        parent::__construct('app:tcp-client');
    }

    public function configure()
    {
        parent::configure();
        $this->setDescription('连接 TCP 服务端并收发消息');
    }

    public function handle()
    {
        $client = new AppTcpClient();
        $client->configure(ClientConfig::forTcp([
            'host' => '127.0.0.1',
            'port' => 9501,
            'framing' => 'eof',
            'delimiter' => "\n",
        ]));

        // Hyperf 命令默认在协程中，auto 会选 swoole 协程客户端，连接是同步的
        $client->connect();

        // 连接后随时可发：从终端逐行读取
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

## 第 8 步：运行客户端

```bash
# 终端 A：启动服务端
php bin/hyperf.php tcp-socket:serve

# 终端 B：运行客户端
php bin/hyperf.php app:tcp-client
```

客户端会立即发出 `onConnect` 里的消息，之后在终端输入任意内容回车即可发送，输入 `quit` 退出。

> 客户端运行的驱动：Hyperf 命令默认在协程中，`auto` → `swoole`；
> 如果在非协程环境（例如 Laravel）使用，`auto` 会自动改用 `react`。

## 收到消息后怎么处理（服务端）

`onMessage` 收到的是一条完整消息。真实业务通常是：解析 JSON → 按 `type` / `cmd` 分发 → 调业务类 → 回复设备。handler 由容器解析（`$container->get($class)`），所以可以构造函数注入业务类：

```php
<?php

declare(strict_types=1);

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

业务类放在任意位置（例如 `app/Service/DeviceService.php`），通过构造函数注入即可：

```php
<?php

declare(strict_types=1);

namespace App\Service;

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

向**单个设备**下发指令：保存连接后调用 `send()`。例如在业务代码里按设备号找到连接（需要自己在 `register` 时把 `ConnectionInterface` 存到 Redis / 内存表中），再 `$connection->send($json)`。

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
            // 心跳响应，可记录最后活跃时间
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
| WebSocket | 协议层 ping/pong：服务端定时发 ping，客户端**自动回 pong**（包内已实现，server/client 两端都是） | 不需要 |
| TCP | 协议本身没有 ping/pong | 设备端定时发应用层心跳（例如 `{"type":"heartbeat"}`） |
| MQTT | `keep_alive` 由 broker 协议保活 | 不需要（见 MQTT 指南） |

### 服务端：自动断开“死连接”

给服务端加 `heartbeat` 配置即可：

```php
'tcp' => [
    'server' => [
        // ...
        'heartbeat' => [
            'interval' => 30,   // 检测 / 发 ping 的间隔（秒）
            'timeout' => 90,    // 超过多少秒没有任何数据（WebSocket 下为没有 pong）就断开
        ],
    ],
],
```

- **swoole 驱动**：自动映射成 Swoole 的 `heartbeat_check_interval` / `heartbeat_idle_time`；
  WebSocket 服务端还会开启 `open_websocket_ping_frame`，由 Swoole 发送真正的 ping 帧。
- **react 驱动**：WebSocket 服务端每隔 `interval` 秒发 ping 帧，`timeout` 内没回 pong 就断开；
  TCP 服务端没有协议层 ping，只做“空闲超过 `timeout` 秒就断开”。
- 不配置 `heartbeat`（或设为 `null`）就关闭该功能。

### 设备端：定时发心跳（TCP）

TCP 没有协议层 ping，设备必须自己定时发心跳。在客户端的 `onConnect()` 里启动定时器（Hyperf 协程环境用 `Swoole\Timer`）：

```php
public function onConnect(): void
{
    $this->send(json_encode(['type' => 'register', 'device_id' => 'dev-1']));

    // 每 30 秒发一次心跳；服务端 handler 收到 heartbeat 回 pong
    \Swoole\Timer::tick(30000, function (): void {
        $this->send(json_encode(['type' => 'heartbeat', 'ts' => time()]));
    });
}
```

服务端 `onMessage` 里对应的分支见上文（`case 'heartbeat'` 回复 `pong`）。这样即使设备长时间没有业务数据，连接也不会被服务端的心跳检查断开。

### 设备端：WebSocket

如果服务端开启了 `heartbeat`，它会定时发 WebSocket ping 帧；本包的 `AbstractWebSocketClient`（以及浏览器）会自动回 pong，**设备端不需要写任何保活代码**。

## 常见问题

| 现象 | 处理 |
| --- | --- |
| `TCP connection failed (errCode 61)` | 服务端没启动，或端口/地址不对 |
| `Address already in use` | 端口被占用，用 `--port=` 换端口或停掉占用进程 |
| 提示 Configure `...handler...` | `config/autoload/tcp-socket.php` 没建，或 `handler` 还是 `null` |
| 提示 Handler class does not exist | 第 3 步的类没建，或命名空间/类名写错 |
| 消息粘包或收不到 | `framing` 用 `eof`/`length`，且客户端与服务端配置要一致 |
| `Type of ...::$name must be ?string` | 装的是 v1.0.0，执行 `composer update hk100/tcp-socket` |
