# hk100/tcp-socket

一个同时兼容 **Hyperf** 与 **Laravel** 的多协议通信包，支持 **TCP Socket**、**WebSocket**、**MQTT**。

- TCP / WebSocket：既提供 **服务端**（命令一键启动、控制台打印监听地址），也提供 **客户端**。
- MQTT：提供 **客户端**（连接、自动订阅、发布）。
- 网络层做了 **驱动抽象**：`swoole`（Swoole/OpenSwoole）与 `react`（纯 PHP 事件循环），`auto` 自动探测。
- 两个框架下都会 **自动注册命令**，无需手动配置命令列表。
- 用法是 **继承基类、实现少量方法**：handler（服务端）与 client（客户端）。

## 特性

- TCP / WebSocket 服务端 & 客户端，Swoole 与 ReactPHP 双驱动
- 内置 WebSocket 握手与帧编解码（不依赖 Ratchet，避免与 symfony 6/7 冲突）
- TCP 分帧：`raw` / `eof` / `length-prefix`
- MQTT 连接、自动订阅、发布、循环消费
- Laravel 包自动发现（`extra.laravel.providers`）
- Hyperf 自动发现（`extra.hyperf.config`）
- 命令启动并在控制台打印监听地址
- 每连接上下文（`setContext/getContext`），方便保存设备 ID 等状态

## 环境要求

- PHP `^8.1`
- Composer
- 可选：`ext-swoole` 或 `ext-openswoole`（使用 `swoole` 驱动时需要）

## 安装

```bash
composer require hk100/tcp-socket
```

要求在 Hyperf / Laravel 项目中，包会自动发现并注册服务与命令。

## 核心概念

| 类型 | 基类 | 用途 |
| --- | --- | --- |
| TCP 服务端 | `Server\AbstractTcpServerHandler` | 处理设备 TCP 连接 |
| WebSocket 服务端 | `Server\AbstractWebSocketServerHandler` | 处理 WebSocket 连接 |
| TCP 客户端 | `Client\AbstractTcpClient` | 主动连接 TCP 服务端 |
| WebSocket 客户端 | `Client\AbstractWebSocketClient` | 主动连接 WebSocket 服务端 |
| MQTT 客户端 | `Mqtt\AbstractMqttClient` | 连接 broker、订阅、发布 |

服务端 handler 的回调：`onOpen` / `onMessage` / `onClose` / `onError`。
客户端 handler 的回调：`onConnect` / `onMessage` / `onClose` / `onError`。
MQTT 回调：`onMessage`，以及可选的 `onConnect` / `onSubscribe` / `onDisconnect`。

## 服务端快速开始

### 1. 编写 handler

```php
namespace App\Socket;

use Hk100\TcpSocket\Contracts\ConnectionInterface;
use Hk100\TcpSocket\Message\Message;
use Hk100\TcpSocket\Server\AbstractTcpServerHandler;

class DeviceTcpHandler extends AbstractTcpServerHandler
{
    public function onOpen(ConnectionInterface $connection): void
    {
        // 设备接入时，可保存设备信息到连接上下文
        $connection->setContext('connected_at', time());
    }

    public function onMessage(ConnectionInterface $connection, Message $message): void
    {
        // $message->payload 是已完成分帧的一条消息
        $connection->send('received:' . $message->payload);
    }

    public function onClose(ConnectionInterface $connection): void
    {
    }
}
```

WebSocket 同理，继承 `AbstractWebSocketServerHandler` 即可（`Message` 带有 `binary` 标记）。

### 2. 配置

Laravel：发布配置并把 `handler` 指向你的类。

```bash
php artisan vendor:publish --tag=tcp-socket-config
```

```php
// config/tcp-socket.php
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

Hyperf：复制示例配置到 `config/autoload/tcp-socket.php`（见 `examples/config/hyperf-tcp-socket.php`）。

### 3. 启动

Laravel：

```bash
php artisan tcp-socket:serve
php artisan tcp-socket:ws-serve
php artisan tcp-socket:mqtt-listen
```

Hyperf：

```bash
php bin/hyperf.php tcp-socket:serve
php bin/hyperf.php tcp-socket:ws-serve
php bin/hyperf.php tcp-socket:mqtt-listen
```

输出示例：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: react, framing: eof)
WebSocket server listening at ws://0.0.0.0:9502 (driver: swoole)
MQTT client connecting to mqtt://127.0.0.1:1883, subscribing to: device/+/data
```

命令参数（`tcp-socket:serve` / `tcp-socket:ws-serve`）：

| 参数 | 说明 |
| --- | --- |
| `--driver=` | `auto` / `swoole` / `react`，覆盖配置 |
| `--host=` | 覆盖监听地址 |
| `--port=` | 覆盖监听端口 |

## 客户端用法（不使用服务端）

直接继承 client 基类，实现回调，配置服务器地址后连接。

```php
namespace App\Socket;

use Hk100\TcpSocket\Client\AbstractTcpClient;
use Hk100\TcpSocket\Message\Message;

class AppTcpClient extends AbstractTcpClient
{
    public function onConnect(): void
    {
        $this->send("hello\n"); // 分帧由底层自动处理
    }

    public function onMessage(Message $message): void
    {
        // 处理 $message->payload
    }
}
```

```php
use Hk100\TcpSocket\Client\ClientConfig;

$client = new AppTcpClient();
$client->configure(ClientConfig::forTcp([
    'host' => '127.0.0.1',
    'port' => 9501,
    'framing' => 'eof',
    'delimiter' => "\n",
]));
$client->connect();
$client->run(); // 阻塞运行（react）/ 协程等待（swoole）
```

WebSocket 客户端：

```php
use Hk100\TcpSocket\Client\AbstractWebSocketClient;

class AppWsClient extends AbstractWebSocketClient
{
    public function onConnect(): void
    {
        $this->send('hello');
    }
}

$client = new AppWsClient();
$client->configure(ClientConfig::forWebSocket(['url' => 'ws://127.0.0.1:9502']));
$client->connect();
$client->run();
```

> 也可以在容器中通过 `Hk100\TcpSocket\Client\ClientBuilder` 组装 client（`attachTcp` / `attachWebSocket`）。

## MQTT 用法

```php
namespace App\Mqtt;

use Hk100\TcpSocket\Mqtt\AbstractMqttClient;
use Hk100\TcpSocket\Mqtt\MqttMessage;

class DeviceMqttClient extends AbstractMqttClient
{
    public function onMessage(MqttMessage $message): void
    {
        // $message->topic / $message->payload / $message->retain
    }
}
```

```php
// config/tcp-socket.php
'mqtt' => [
    'host' => '127.0.0.1',
    'port' => 1883,
    'client_id' => null,            // null 自动生成
    'username' => null,
    'password' => null,
    'handler' => App\Mqtt\DeviceMqttClient::class,
    'subscriptions' => [
        'device/+/data' => 0,
    ],
],
```

执行 `tcp-socket:mqtt-listen` 后：连接 broker → 自动订阅所有 `subscriptions` → 进入消费循环。

手工使用：

```php
use Hk100\TcpSocket\Mqtt\MqttConfig;

$client = new DeviceMqttClient();
$client->configure(MqttConfig::fromArray(config('tcp-socket.mqtt')));
$client->run();
```

## 分帧（Framing）

TCP 是字节流，需要界定“一条消息”。`framing` 配置：

| 类型 | 说明 | 编码行为 |
| --- | --- | --- |
| `raw` | 每个 TCP 数据块即一条消息 | 原样发送 |
| `eof` | 按分隔符切分（默认 `\n`） | 发送时追加分隔符 |
| `length` | 长度前缀（1/2/4 字节大端） | 发送时写入长度头 |

分帧对业务透明：handler 拿到的就是“一条消息”，`$connection->send()` 传入的是消息内容。

## 驱动说明

`driver` 可选 `auto`（默认）/ `swoole` / `react`。

- `react`：纯 PHP（ReactPHP），Laravel、Hyperf、无框架脚本都能用，无需扩展。
- `swoole`：性能更好，需要 `ext-swoole` / `ext-openswoole`。
- `auto`：装了 Swoole 时优先 `swoole`；客户端在**非协程环境**下自动退回 `react`（因为 Swoole 协程客户端必须在协程内使用）。

### Hyperf 注意事项

Hyperf 命令默认运行在协程中，而 `Swoole\Server` 不能在协程事件循环里创建。本包已把服务端命令声明为 `$coroutine = false`，因此在命令中可以直接启动独立服务器（`swoole` 或 `react` 驱动均可）。

- 服务端命令：`$coroutine = false`，可在命令进程内启动服务器。
- Swoole 客户端：只能在协程内使用（如 Hyperf 协程、`Swoole\Coroutine\run()`）。在普通 Laravel 请求/命令中用 client 时，`auto` 会选 `react`。

## 配置参考

```php
return [
    'driver' => 'auto',

    'tcp' => [
        'server' => [
            'host' => '0.0.0.0', 'port' => 9501,
            'handler' => null,
            'framing' => 'eof', 'delimiter' => "\n", 'length_bytes' => 4,
            'options' => [], // 透传给 Swoole\Server::set()
        ],
        'client' => [
            'host' => '127.0.0.1', 'port' => 9501, 'connect_timeout' => 5,
            'handler' => null,
            'framing' => 'eof', 'delimiter' => "\n", 'length_bytes' => 4,
        ],
    ],

    'websocket' => [
        'server' => [
            'host' => '0.0.0.0', 'port' => 9502, 'path' => '/',
            'handler' => null, 'options' => [],
        ],
        'client' => [
            'url' => 'ws://127.0.0.1:9502', 'connect_timeout' => 5, 'handler' => null,
        ],
    ],

    'mqtt' => [
        'host' => '127.0.0.1', 'port' => 1883,
        'client_id' => null, 'username' => null, 'password' => null,
        'clean_session' => true, 'keep_alive' => 60, 'connect_timeout' => 5,
        'tls' => null, // ['ca_file' => ..., 'verify_peer' => true]
        'handler' => null,
        'subscriptions' => [],
    ],
];
```

> 缺少配置文件也能运行：核心内置默认值（`Hk100\TcpSocket\Support\Defaults`）。

## 示例

- `examples/handlers/`：TCP / WebSocket / MQTT 的 handler 示例（拷进应用即可）
- `examples/config/`：Laravel 与 Hyperf 的配置示例
- `examples/standalone/`：无需框架的回环服务端脚本

```bash
# React TCP echo
php examples/standalone/react-tcp-echo.php 0.0.0.0 9501
printf 'hello\n' | nc 127.0.0.1 9501

# Swoole TCP echo（需 ext-swoole）
php examples/standalone/swoole-tcp-echo.php 0.0.0.0 9501

# React WebSocket echo
php examples/standalone/react-ws-echo.php 0.0.0.0 9502
websocat ws://127.0.0.1:9502
```

## 测试

```bash
composer install
vendor/bin/phpunit
```

## 发布到 Packagist

1. 在 GitHub 创建仓库并推送到远程。
2. 打一个语义化版本 tag：

   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. 打开 <https://packagist.org/packages/submit>，粘贴仓库 URL，提交。
4. 配置 GitHub Webhook（Packagist 会给出）以便后续 tag 自动同步。

发布后用户即可 `composer require hk100/tcp-socket`。

## 需要提交到 Git 的文件

提交：

```text
composer.json
README.md
LICENSE
CHANGELOG.md
.gitignore
.editorconfig
phpunit.xml.dist
config/
src/
examples/
tests/
```

忽略（见 `.gitignore`）：

```text
/vendor/
composer.lock
/build/
.phpunit.cache/
.phpunit.result.cache
.idea/  .vscode/  .DS_Store  *.log
```

> 作为库项目，通常不提交 `composer.lock`（由使用者项目自行锁定依赖）。

## 许可证

MIT，详见 [LICENSE](LICENSE)。
