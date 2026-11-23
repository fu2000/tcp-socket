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

## 常见问题

| 现象 | 处理 |
| --- | --- |
| `TCP connection failed (errCode 61)` | 服务端没启动，或端口/地址不对 |
| `Address already in use` | 端口被占用，用 `--port=` 换端口或停掉占用进程 |
| 提示 Configure `...handler...` | `config/autoload/tcp-socket.php` 没建，或 `handler` 还是 `null` |
| 提示 Handler class does not exist | 第 3 步的类没建，或命名空间/类名写错 |
| 消息粘包或收不到 | `framing` 用 `eof`/`length`，且客户端与服务端配置要一致 |
| `Type of ...::$name must be ?string` | 装的是 v1.0.0，执行 `composer update hk100/tcp-socket` |
