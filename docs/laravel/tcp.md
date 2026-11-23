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

## 常见问题

| 现象 | 处理 |
| --- | --- |
| `TCP connection failed` / `Connection refused` | 服务端没启动，或端口/地址不对 |
| `Address already in use` | 端口被占用，加 `--port=` 换端口 |
| 找不到 `tcp-socket:*` 命令 | `php artisan optimize:clear` |
| 提示 Configure `...handler...` | `config/tcp-socket.php` 里 `handler` 为 `null` 或类名错 |
| 消息粘包 / 收不到 | `framing` 用 `eof`/`length`，服务端与客户端要一致 |
| 发送返回 false | 还没连上（React 异步），把首条发送放到 `onConnect()` |
