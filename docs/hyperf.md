# Hyperf 使用指南（一步一步）

适用于 **Hyperf 3.x**，PHP `^8.1`。按顺序操作即可。

> 命令统一用 `php bin/hyperf.php <命令>`。如果你的项目里有自定义的 `artisan`
> 包装脚本，它最终也是执行 `bin/hyperf.php`，两种写法等价。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

包发布到 Packagist 之前，用 VCS 方式安装：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.1
```

Hyperf 会自动发现 `extra.hyperf.config`（`ConfigProvider`），不需要手动注册命令。

检查命令是否注册成功：

```bash
php bin/hyperf.php list | grep tcp-socket
```

应看到 `tcp-socket:serve`、`tcp-socket:ws-serve`、`tcp-socket:mqtt-listen`。

> 如果报 `Type of ... TcpServerCommand::$name must be ?string`，说明装的是 v1.0.0。
> 升级到 v1.0.1 即可修复：`composer update hk100/tcp-socket`。

## 第 2 步：创建配置文件（**必须手动做**）

Hyperf **不会自动生成**配置文件。没有这个文件，命令会报
`Configure tcp-socket.tcp.server.handler ...`。

从包内模板复制：

```bash
cp vendor/hk100/tcp-socket/config/tcp-socket.php config/autoload/tcp-socket.php
```

包内有两个模板，二选一：

| 模板路径（安装后位于 vendor 内） | 说明 |
| --- | --- |
| `vendor/hk100/tcp-socket/config/tcp-socket.php` | 默认配置，所有 `handler` 为 `null`，需要自己填 |
| `vendor/hk100/tcp-socket/examples/config/hyperf-tcp-socket.php` | 带 `App\Socket\...` 示例类名的配置 |

推荐用第一个，然后按下面步骤填 handler。

## 第 3 步：创建 TCP 服务端 handler

新建 `app/Socket/DeviceTcpHandler.php`：

```php
<?php

declare(strict_types=1);

namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
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
    }
}
```

## 第 4 步：把 handler 写进配置

编辑 **`config/autoload/tcp-socket.php`**，找到 `tcp => server`，设置 `handler`：

```php
'tcp' => [
    'server' => [
        'host' => '0.0.0.0',
        'port' => 9501,
        'handler' => App\Socket\DeviceTcpHandler::class,
        'framing' => 'eof',
        'delimiter' => "\n",
    ],
    // client 保持模板内容即可
],
```

## 第 5 步：启动 TCP 服务端并测试

```bash
php bin/hyperf.php tcp-socket:serve
```

输出：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: swoole, framing: eof)
```

另开终端测试：

```bash
printf 'hello\n' | nc 127.0.0.1 9501
# received: hello
```

没有 `nc` 时：

```bash
php -r '$f = stream_socket_client("tcp://127.0.0.1:9501"); fwrite($f, "hello\n"); echo fgets($f);'
```

常用参数：

```bash
php bin/hyperf.php tcp-socket:serve --port=9503
php bin/hyperf.php tcp-socket:serve --driver=react   # 强制纯 PHP 驱动
```

> Hyperf 装了 Swoole 时 `auto` 会用 `swoole` 驱动。服务端命令在本包内已声明为
> 非协程执行（`$coroutine = false`），所以能直接创建 `Swoole\Server`，无需你额外处理。

## 第 6 步：WebSocket 服务端

新建 `app/Socket/DeviceWebSocketHandler.php`：

```php
<?php

declare(strict_types=1);

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

在 `config/autoload/tcp-socket.php` 中设置：

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
php bin/hyperf.php tcp-socket:ws-serve
```

测试（浏览器控制台）：

```js
const ws = new WebSocket('ws://127.0.0.1:9502/');
ws.onmessage = (e) => console.log(e.data);
ws.onopen = () => ws.send('hello');
```

## 第 7 步：客户端（不启动服务端）

客户端用于**主动连接别人的服务器**。新建 `app/Socket/AppTcpClient.php`：

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
        $this->send('hello'); // 分帧自动处理，不要手动加 "\n"
    }

    public function onMessage(Message $message): void
    {
        logger()->info('tcp message', ['payload' => $message->payload]);
    }

    public function onError(\Throwable $exception): void
    {
        logger()->error('tcp client error: ' . $exception->getMessage());
    }
}
```

新建命令 `app/Command/RunTcpClientCommand.php`：

```php
<?php

declare(strict_types=1);

namespace App\Command;

use App\Socket\AppTcpClient;
use Hk100\TcpSocket\Client\ClientConfig;
use Hyperf\Command\Command;

class RunTcpClientCommand extends Command
{
    protected ?string $name = 'app:tcp-client';

    protected string $description = 'Connect to the remote TCP server';

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
        $client->run(); // 保持运行；Ctrl+C 退出

        return 0;
    }
}
```

运行：

```bash
php bin/hyperf.php app:tcp-client
```

> 命令默认在协程中运行，`auto` 会自动选择 `swoole` 协程客户端。
> 如果在非协程环境使用客户端，`auto` 会自动退回 `react`。

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

declare(strict_types=1);

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
    }
}
```

在 `config/autoload/tcp-socket.php` 中设置：

```php
'mqtt' => [
    'host' => '127.0.0.1',
    'port' => 1883,
    'client_id' => null,
    'username' => null,
    'password' => null,
    'handler' => App\Mqtt\DeviceMqttClient::class,
    'subscriptions' => [
        'device/+/data' => 0,
    ],
],
```

启动：

```bash
php bin/hyperf.php tcp-socket:mqtt-listen
```

输出：

```text
MQTT client connecting to mqtt://127.0.0.1:1883, subscribing to: device/+/data
```

## 常见问题

| 现象 | 处理 |
| --- | --- |
| `Type of ...::$name must be ?string` | 装的是 v1.0.0，升级：`composer update hk100/tcp-socket` |
| 提示 Configure `...handler...` | 没有创建 `config/autoload/tcp-socket.php`，或 `handler` 还是 `null` |
| 提示 Handler class `...` does not exist | 第 3/6/8 步的类没创建，或命名空间/类名写错 |
| 找不到 `tcp-socket:*` 命令 | 确认 `vendor/hk100/tcp-socket` 已安装；执行 `composer dump-autoload` |
| 端口被占用 | 加 `--port=9503` 或改配置 |
| 消息粘在一起 | TCP 用 `framing` = `eof` 或 `length`，不要用 `raw` |
| 想用纯 PHP 驱动 | 加 `--driver=react`，或不装 Swoole 扩展 |
