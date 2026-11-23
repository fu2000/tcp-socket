# hk100/tcp-socket

同时兼容 **Hyperf** 与 **Laravel** 的多协议通信包：**TCP Socket / WebSocket / MQTT**。

- TCP、WebSocket：**服务端 + 客户端**
- MQTT：**客户端**（连接、自动订阅、发布）
- 服务端用命令一键启动，控制台打印监听地址
- 网络层双驱动：`swoole`（Swoole/OpenSwoole）与 `react`（纯 PHP），`auto` 自动选择
- 两个框架都会**自动发现并注册命令**，不需要手动配置命令列表
- 用法是**继承基类、实现少量方法**
- **不依赖 Laravel Echo / Reverb / 广播系统**，TCP、WebSocket、MQTT 全部为原生实现

## 文档导航

每个协议一份独立指南，包含完整的建文件 → 配置 → 启动 → 收发消息步骤。

| 协议 | Laravel | Hyperf |
| --- | --- | --- |
| TCP Socket | [docs/laravel/tcp.md](docs/laravel/tcp.md) | [docs/hyperf/tcp.md](docs/hyperf/tcp.md) |
| WebSocket | [docs/laravel/websocket.md](docs/laravel/websocket.md) | [docs/hyperf/websocket.md](docs/hyperf/websocket.md) |
| MQTT | [docs/laravel/mqtt.md](docs/laravel/mqtt.md) | [docs/hyperf/mqtt.md](docs/hyperf/mqtt.md) |

框架入口（安装、配置创建、命令表、注意事项）：

- [Laravel 使用指南](docs/laravel/README.md)
- [Hyperf 使用指南](docs/hyperf/README.md)

> 第一次使用请直接看对应框架的索引，再按协议进入具体指南操作即可。

## 环境要求

- PHP `^8.1`
- Composer
- 可选：`ext-swoole` 或 `ext-openswoole`（使用 `swoole` 驱动时需要；只用 `react` 驱动则不需要）

## 安装

```bash
composer require hk100/tcp-socket
```

包发布到 Packagist 之前，可以从 GitHub 作为 VCS 仓库安装：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0
```

安装后：

- Laravel：`extra.laravel.providers` 自动发现，无需修改 `config/app.php`
- Hyperf：`extra.hyperf.config` 自动发现，无需手动注册 `ConfigProvider`

## 核心概念

| 类型 | 基类 | 用途 |
| --- | --- | --- |
| TCP 服务端 | `Server\AbstractTcpServerHandler` | 处理设备 TCP 连接 |
| WebSocket 服务端 | `Server\AbstractWebSocketServerHandler` | 处理 WebSocket 连接 |
| TCP 客户端 | `Client\AbstractTcpClient` | 主动连接 TCP 服务端 |
| WebSocket 客户端 | `Client\AbstractWebSocketClient` | 主动连接 WebSocket 服务端 |
| MQTT 客户端 | `Mqtt\AbstractMqttClient` | 连接 broker、订阅、发布 |

回调：

- 服务端 handler：`onOpen` / `onMessage` / `onClose` / `onError`
- 客户端 handler：`onConnect` / `onMessage` / `onClose` / `onError`
- MQTT：`onMessage`，可选 `onConnect` / `onSubscribe` / `onDisconnect`

## 命令

| 命令 | 作用 | Laravel | Hyperf |
| --- | --- | --- | --- |
| `tcp-socket:serve` | 启动 TCP 服务端 | `php artisan tcp-socket:serve` | `php bin/hyperf.php tcp-socket:serve` |
| `tcp-socket:ws-serve` | 启动 WebSocket 服务端 | `php artisan tcp-socket:ws-serve` | `php bin/hyperf.php tcp-socket:ws-serve` |
| `tcp-socket:mqtt-listen` | 连接 MQTT 并订阅 | `php artisan tcp-socket:mqtt-listen` | `php bin/hyperf.php tcp-socket:mqtt-listen` |

`tcp-socket:serve` / `tcp-socket:ws-serve` 支持参数：

| 参数 | 说明 |
| --- | --- |
| `--driver=` | `auto` / `swoole` / `react`，覆盖配置 |
| `--host=` | 覆盖监听地址 |
| `--port=` | 覆盖监听端口 |

启动后会打印监听地址：

```text
TCP server listening at tcp://0.0.0.0:9501 (driver: swoole, framing: eof)
WebSocket server listening at ws://0.0.0.0:9502 (driver: swoole)
MQTT client connecting to mqtt://127.0.0.1:1883, subscribing to: device/+/data
```

## 分帧（Framing）

TCP 是字节流，需要界定“一条消息”。`framing` 配置：

| 类型 | 说明 | 发送行为 |
| --- | --- | --- |
| `raw` | 每个 TCP 数据块即一条消息 | 原样发送 |
| `eof` | 按分隔符切分（默认 `\n`） | 自动追加分隔符 |
| `length` | 长度前缀（1/2/4 字节大端） | 自动写入长度头 |

分帧对业务透明：handler 收到的就是“一条消息”，`$connection->send()` 传入消息内容即可。

## 心跳 / 保活

服务端可以开启心跳，自动踢掉已经掉线的设备：

```php
'tcp' => [
    'server' => [
        // ...
        'heartbeat' => ['interval' => 30, 'timeout' => 90],
    ],
],
'websocket' => [
    'server' => [
        // ...
        'heartbeat' => ['interval' => 30, 'timeout' => 90],
    ],
],
```

| 协议 | 服务端行为 | 设备端/客户端 |
| --- | --- | --- |
| WebSocket | 每 `interval` 秒发 ping，`timeout` 内没回 pong 就断开 | **自动回 pong**（包内已实现，浏览器也是），无需写代码 |
| TCP | 超过 `timeout` 秒没有任何数据就断开（Swoole 驱动用原生心跳检测） | 需要定时发应用层心跳，例如 `{"type":"heartbeat"}` |
| MQTT | broker 协议保活 | 客户端库按 `keep_alive` 自动 PING，无需写代码 |

- `swoole` 驱动：TCP 服务端映射为 `heartbeat_check_interval` / `heartbeat_idle_time`；
  WebSocket 服务端用定时器发 ping 帧，并按 Swoole 记录的连接最后活跃时间断开超时客户端。
- `react` 驱动：WebSocket 服务端自己发 ping 帧；TCP 服务端做空闲检测（TCP 协议没有 ping）。
- TCP 设备端心跳定时器的写法见各框架指南的「保活（心跳）」一节。

## 驱动说明

`driver` 可选 `auto`（默认）/ `swoole` / `react`。

- `react`：纯 PHP（ReactPHP），Laravel、Hyperf、无框架脚本都能用，无需扩展。
- `swoole`：性能更好，需要 `ext-swoole` / `ext-openswoole`。
- `auto`：装了 Swoole 时优先 `swoole`；客户端在**非协程环境**下自动退回 `react`
  （Swoole 协程客户端必须在协程内使用）。

服务端命令在 Hyperf 中会以非协程方式运行（包内已设置 `$coroutine = false`），
因此可以在命令进程里直接启动独立的 Swoole 服务器或 React 事件循环。

## 配置结构

配置键为 `tcp-socket.*`，两个框架结构一致：

```text
tcp-socket.driver
tcp-socket.tcp.server        host / port / handler / framing / delimiter / length_bytes / heartbeat / options
tcp-socket.tcp.client        host / port / connect_timeout / framing ...
tcp-socket.websocket.server  host / port / path / handler / heartbeat / options
tcp-socket.websocket.client  url / connect_timeout
tcp-socket.mqtt              host / port / username / password / handler / subscriptions ...
```

完整配置示例与每一步操作见：

- Laravel → [docs/laravel/README.md](docs/laravel/README.md)
- Hyperf → [docs/hyperf/README.md](docs/hyperf/README.md)

> 缺少配置文件也能启动（使用 `Hk100\TcpSocket\Support\Defaults` 内置默认值），
> 但 `handler` 必须配置，否则命令会提示配置错误。

## 示例

- `examples/handlers/`：TCP / WebSocket / MQTT handler 示例
- `examples/config/`：Laravel 与 Hyperf 配置示例
- `examples/standalone/`：无需框架的回环服务端脚本

```bash
php examples/standalone/react-tcp-echo.php 0.0.0.0 9501
printf 'hello\n' | nc 127.0.0.1 9501

php examples/standalone/react-ws-echo.php 0.0.0.0 9502
# 浏览器控制台: new WebSocket('ws://127.0.0.1:9502')
```

## 测试

```bash
composer install
vendor/bin/phpunit
HK100_RUN_SWOOLE_TESTS=1 vendor/bin/phpunit   # 需要 ext-swoole
```

## 发布到 Packagist

1. 在 GitHub 创建仓库并推送。
2. 打 tag：

   ```bash
   git tag v1.0.1
   git push origin v1.0.1
   ```

3. 打开 <https://packagist.org/packages/submit>，粘贴仓库 URL。
4. 配置 Packagist 给出的 GitHub Webhook，之后推 tag 会自动同步。

> 包名与 GitHub 账号名不必一致，Packagist 以 `composer.json` 的 `name` 为准。

## 需要提交到 Git 的文件

提交：

```text
composer.json  README.md  LICENSE  CHANGELOG.md  .gitignore  .editorconfig  phpunit.xml.dist
config/  docs/  src/  examples/  tests/
```

忽略（见 `.gitignore`）：`/vendor/`、`composer.lock`、`.phpunit.cache/`、`.idea/`、`.vscode/`、`.DS_Store`、`*.log`。

> 作为库项目，通常不提交 `composer.lock`。

## 许可证

MIT，详见 [LICENSE](LICENSE)。
