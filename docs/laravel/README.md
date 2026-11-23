# Laravel 使用指南（索引）

Laravel 10 / 11 接入 `hk100/tcp-socket` 的入口文档。按协议选择下面的指南，每篇都是从安装到实测的完整步骤。

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

2. Laravel 通过 `extra.laravel.providers` 自动发现 ServiceProvider，无需改 `config/app.php`。

3. 发布配置文件：

   ```bash
   php artisan vendor:publish --tag=tcp-socket-config
   ```

   生成 **`config/tcp-socket.php`**，所有协议都在这个文件里配置。

4. 确认命令已注册：

   ```bash
   php artisan list | grep tcp-socket
   ```

## 命令

| 命令 | 作用 |
| --- | --- |
| `php artisan tcp-socket:serve` | 启动 TCP 服务端 |
| `php artisan tcp-socket:ws-serve` | 启动 WebSocket 服务端 |
| `php artisan tcp-socket:mqtt-listen` | 连接 MQTT broker 并订阅 |

服务端命令支持 `--driver=auto|swoole|react`、`--host=`、`--port=`。

## Laravel 下必须知道的几点

- **不使用 Laravel Echo / Reverb / 广播系统**：TCP、WebSocket、MQTT 都是原生实现。
- **驱动**：`auto` 在 Laravel 命令（非协程环境）下默认用 `react`（纯 PHP），不装 Swoole 也能跑；
  想用 `swoole` 需安装扩展并显式 `--driver=swoole`。
- **客户端 `connect()` 是异步的（React 驱动）**：首条消息放在 `onConnect()` 里发，或参考各指南的
  React 事件循环交互示例。
- **TCP 分帧**：服务端与客户端的 `framing` / `delimiter` 必须一致；`eof` 分帧发送时包会自动补分隔符，
  不要手动再加 `"\n"`。
