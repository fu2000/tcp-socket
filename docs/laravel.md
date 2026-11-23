# Laravel 使用指南（一步一步）

适用于 **Laravel 10 / 11**，PHP `^8.1`。按顺序操作即可。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

包发布到 Packagist 之前，用 VCS 方式安装：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0
```

Laravel 会自动发现 ServiceProvider（`extra.laravel.providers`），不需要改 `config/app.php`。

检查命令是否注册成功：

```bash
php artisan list | grep tcp-socket
```

应看到 `tcp-socket:serve`、`tcp-socket:ws-serve`、`tcp-socket:mqtt-listen` 三个命令。
如果没看到，执行 `php artisan optimize:clear` 后重试。

## 第 2 步：生成配置文件

```bash
php artisan vendor:publish --tag=tcp-socket-config
```

执行后会生成 **`config/tcp-socket.php`**。所有配置都改这个文件。

## 第 3 步：创建 TCP 服务端 handler

新建 `app/Socket/DeviceTcpHandler.php`：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        // 设备接入，可保存状态到连接上下文
        $connection->setContext('connected_at', time());
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        // $message->payload 已经是分帧后的一条完整消息
        $connection->send('received: ' . $message->payload);
        // eof 分帧会自动追加分隔符，不需要自己加 "\n"
    }

    public function onClose(ConnectionInterface $connection): void
    {
        // 连接关闭
    }
}
```

## 第 4 步：配置 handler

编辑 `config/tcp-socket.php`，把 `tcp.server` 改成：

```php
'tcp' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'handler' => App\Socket\DeviceTcpHandler::class,
        'framing' => 'eof',
        'delimiter' => "\n",
    ],
],
```

## 第 5 步：启动 TCP 服务端并测试

```bash
php artisan tcp-socket:serve
```

输出：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: react, framing: eof)
```

另开一个终端测试（`nc` 需自己安装；没有 `nc` 用下面的 PHP 命令）：

```bash
printf 'hello\n' | nc 127.0.0.1 9501
# received: hello
```

```bash
php -r '$f = stream_socket_client("tcp://127.0.0.1:9501"); fwrite($f, "hello\n"); echo fgets($f);'
```

可用参数：

```bash
php artisan tcp-socket:serve --port=9503
php artisan tcp-socket:serve --driver=react
```

## 第 6 步：WebSocket 服务端

新建 `app/Socket/DeviceWebSocketHandler.php`：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractWebSocketServerHandler;

class DeviceWebSocketHandler extends AbstractWebSocketServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        $connection->send('echo: ' . $message->payload);
    }

    public function onClose(ConnectionInterface $connection): void
    {
    }
}
```

配置：

```php
'websocket' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9502,
        'path' => '/',
        'handler' => App\Socket\DeviceWebSocketHandler::class,
    ],
],
```

启动：

```bash
php artisan tcp-socket:ws-serve
```

测试（浏览器控制台）：

```js
const ws = new WebSocket('ws://127.0.0.1:9502/');
ws.onmessage = (e) => console.log(e.data);
ws.onopen = () => ws.send('hello');
```

## 第 7 步：客户端（不启动服务端）

客户端用于**主动连接别人的服务器**，比如对接设备网关。

新建 `app/Socket/AppTcpClient.php`：

```php
<?php

namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractTcpClient;
use Hk100\TcpSocket\Message\Message;

class AppTcpClient extends AbstractTcpClient
{
    public function onConnect(): void
    {
        $this->send('hello'); // 分帧自动处理，不要手动加 "\n"
    }

    public function onMessage(Message $message): void
    {
        logger()->info('tcp message', ['payload' => $message->payload]);

        if ($message->payload === 'bye') {
            $this->close();
        }
    }

    public function onError(\Throwable $exception): void
    {
        logger()->error('tcp client error: ' . $exception->getMessage());
    }
}
```

用一个 Artisan 命令运行它（客户端是长连接，需要用常驻命令）：

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

    protected $description = 'Connect to the remote TCP server';

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
        $client->run(); // 阻塞到连接关闭；Ctrl+C 退出

        return self::SUCCESS;
    }
}
```

运行：

```bash
php artisan app:tcp-client
```

> Laravel 的命令/请求环境不是协程，`auto` 驱动会自动选择 `react`（纯 PHP），无需装 Swoole。

WebSocket 客户端同理，继承 `Hk100\TcpSocket\Client\AbstractWebSocketClient`：

```php
use Hk100\TcpSocket\Client\ClientConfig;

$client = new AppWsClient();
$client->configure(ClientConfig::forWebSocket([
    'url' => 'ws://127.0.0.1:9502/',
]));
$client->connect();
$client->run();
```

## 第 8 步：MQTT 客户端

新建 `app/Mqtt/DeviceMqttClient.php`：

```php
<?php

namespace App\Mqtt;

use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttMessage;

class DeviceMqttClient extends AbstractMqttClient
{
    public function onMessage(MqttMessage $message): void
    {
        logger()->info('mqtt message', [
            'topic' => $message->topic,
            'payload' => $message->payload,
            'retain' => $message->retain,
        ]);

        // 需要回复时可以发布：
        // $this->publish('device/1/cmd', 'on');
    }
}
```

配置：

```php
'mqtt' => [
    'host' => '127.0.0.1',
    'port' => 1883,
    'client_id' => null,          // null 自动生成
    'username' => null,
    'password' => null,
    'handler' => App\Mqtt\DeviceMqttClient::class,
    'subscriptions' => [
        'device/+/data' => 0,     // 连接后自动订阅
    ],
],
```

启动：

```bash
php artisan tcp-socket:mqtt-listen
```

输出：

```text
MQTT client connecting to mqtt://127.0.0.1:1883, subscribing to: device/+/data
```

## 常见问题

| 现象 | 处理 |
| --- | --- |
| 找不到 `tcp-socket:*` 命令 | 执行 `php artisan optimize:clear`；确认 `vendor/hk100/tcp-socket` 已安装 |
| 提示 Configure `...handler...` | `config/tcp-socket.php` 里的 `handler` 为 `null` 或类名写错 |
| 提示 Configured handler 必须继承基类 | handler 没有继承 `AbstractTcpServerHandler` / `AbstractWebSocketServerHandler` |
| 端口被占用 | 加 `--port=9503` 或改配置 |
| 消息粘在一起 | TCP 用 `framing` = `eof` 或 `length`，不要用 `raw` |
| 收不到客户端发的数据 | 检查客户端是否按同样分帧发送（`eof` 需要换行符） |
