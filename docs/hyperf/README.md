# Hyperf 使用指南（索引）

Hyperf 3.x 接入 `hk100/tcp-socket` 的入口文档。按协议选择下面的指南，每篇都是从安装到实测的完整步骤。

## 按协议查看

| 协议 | 指南 | 说明 |
| --- | --- | --- |
| TCP Socket | [tcp.md](tcp.md) | 服务端 handler + 客户端 + 交互发送命令 |
| WebSocket | [websocket.md](websocket.md) | 服务端 handler + 客户端 + 浏览器/终端测试 |
| MQTT | [mqtt.md](mqtt.md) | 连接 broker、自动订阅、发布 |

## 通用步骤

1. 安装：

   ```bash
   composer require hk100/tcp-socket
   ```

   Packagist 发布前：

   ```bash
   composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
   composer require hk100/tcp-socket:^1.0.2
   ```

2. Hyperf 通过 `extra.hyperf.config` 自动发现 `ConfigProvider`，无需手动注册命令。

3. **必须手动创建配置文件**（Hyperf 不会自动生成、也不会合并默认值）：

   ```bash
   cp vendor/hk100/tcp-socket/config/tcp-socket.php config/autoload/tcp-socket.php
   ```

   生成的文件里 `handler` 默认为 `null`，改成你自己的类名即可。

4. 确认命令已注册：

   ```bash
   php bin/hyperf.php list | grep tcp-socket
   ```

## 命令

| 命令 | 作用 |
| --- | --- |
| `php bin/hyperf.php tcp-socket:serve` | 启动 TCP 服务端 |
| `php bin/hyperf.php tcp-socket:ws-serve` | 启动 WebSocket 服务端 |
| `php bin/hyperf.php tcp-socket:mqtt-listen` | 连接 MQTT broker 并订阅 |

服务端命令支持 `--driver=auto|swoole|react`、`--host=`、`--port=`。

## Hyperf 下必须知道的几点

- **服务端命令以非协程方式运行**（包内已设置 `$coroutine = false`），否则无法创建独立的
  `Swoole\Server` 或阻塞 React 事件循环。你不需要做任何处理。
- **驱动**：装了 Swoole 时 `auto` 用 `swoole`；也可 `--driver=react` 跑纯 PHP 版本。
- **客户端**：Hyperf 命令默认在协程中，`auto` 会选 Swoole 协程客户端，`connect()` 是同步的，
  连接完成后即可直接 `send()`。
- **TCP 分帧**：服务端与客户端的 `framing` / `delimiter` 必须一致；`eof` 分帧发送时包会自动补分隔符，
  不要手动再加 `"\n"`。
- **Swoole 日志**：可在 `options` 里指定 `log_file` / `log_level`，避免 `open() failed` 提示。
- **收到消息后怎么处理、保活/心跳**：每篇协议指南里都有「收到消息后怎么处理」和「保活（心跳）」两节，
  包含按 `type` 分发业务、执行服务端推送的指令、服务端 `heartbeat` 配置、设备端定时心跳等完整代码。
