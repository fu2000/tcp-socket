# Hyperf · MQTT 完整指南

Hyperf 3.x 下 **MQTT 客户端**（连接 broker、自动订阅、发布、持续消费）的完整步骤。

> MQTT 只有客户端，没有服务端；需要先有一个 MQTT broker（例如 Mosquitto、EMQX）。

## 第 1 步：安装

```bash
composer require hk100/tcp-socket
```

Packagist 发布前：

```bash
composer config repositories.tcp-socket vcs https://github.com/fu2000/tcp-socket.git
composer require hk100/tcp-socket:^1.0.2
```

## 第 2 步：创建配置文件（必须手动）

```bash
cp vendor/hk100/tcp-socket/config/tcp-socket.php config/autoload/tcp-socket.php
```

## 第 3 步：创建 MQTT 客户端类

新建 **`app/Mqtt/DeviceMqttClient.php`**：

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
        // $message->topic / $message->payload / $message->qos / $message->retain
        $segments = explode('/', $message->topic);
        $deviceId = $segments[1] ?? 'unknown';

        logger()->info('mqtt message', [
            'topic' => $message->topic,
            'device' => $deviceId,
            'payload' => $message->payload,
        ]);

        // 收到 ping 就回复 pong（发布到下行主题）
        if ($message->payload === 'ping') {
            $this->publish("device/{$deviceId}/cmd", 'pong', qos: 0);
        }
    }

    protected function onConnect(): void
    {
        // 连接成功后可发布上线状态
        $this->publish('device/status', 'online', qos: 0, retain: true);
    }

    protected function onSubscribe(string $topic, int $qos): void
    {
        logger()->info("mqtt subscribed: {$topic} (qos {$qos})");
    }

    protected function onDisconnect(): void
    {
        logger()->info('mqtt disconnected');
    }
}
```

`onMessage` 是必须实现的；`onConnect` / `onSubscribe` / `onDisconnect` 可选。

## 第 4 步：配置

编辑 **`config/autoload/tcp-socket.php`**：

```php
'mqtt' => [
    'host' => '127.0.0.1',
    'port' => 1883,
    'client_id' => null,          // null 自动生成
    'username' => null,
    'password' => null,
    'clean_session' => true,
    'keep_alive' => 60,
    'connect_timeout' => 5,
    'handler' => App\Mqtt\DeviceMqttClient::class,
    // 连接后自动订阅：主题 => QoS
    'subscriptions' => [
        'device/+/data' => 0,
        'device/+/cmd' => 1,
    ],
],
```

启用 TLS：

```php
'tls' => [
    'ca_file' => '/path/to/ca.crt',
    'verify_peer' => true,
],
```

## 第 5 步：启动订阅命令

```bash
php bin/hyperf.php tcp-socket:mqtt-listen
```

输出：

```text
MQTT client connecting to mqtt://127.0.0.1:1883, subscribing to: device/+/data, device/+/cmd
```

之后：连接 broker → 自动订阅 `subscriptions` 里的所有主题 → 进入阻塞消费循环，消息逐条进入 `onMessage`。

## 第 6 步：测试

用 Mosquitto 命令行工具（`brew install mosquitto` 或 `apt install mosquitto-clients`）：

```bash
# 发一条消息到 device/1/data，订阅端会收到
mosquitto_pub -h 127.0.0.1 -t device/1/data -m '{"temperature": 23.5}'

# 发 ping，观察客户端是否发布 device/1/cmd = pong
mosquitto_pub -h 127.0.0.1 -t device/1/data -m 'ping'

# 单独订阅下行主题验证
mosquitto_sub -h 127.0.0.1 -t 'device/+/cmd'
```

也可以在自己的代码里发布：

```php
$this->publish('device/1/cmd', 'reboot', qos: 1);
```

## 第 7 步：不用命令、手动运行（可选）

如果要在自定义命令/协程里控制连接：

```php
use Hk100\TcpSocket\Mqtt\MqttConfig;

$client = new \App\Mqtt\DeviceMqttClient();
$client->configure(MqttConfig::fromArray(
    $this->container->get(\Hyperf\Contract\ConfigInterface::class)->get('tcp-socket.mqtt', [])
));

$client->run();         // 连接 + 自动订阅 + 阻塞消费（Ctrl+C 退出）
// 或手动：$client->subscribe('xxx/#'); $client->publish(...); $client->disconnect();
```

## 保活（心跳）

MQTT 的保活由协议层负责：客户端库会按配置的 `keep_alive`（秒）自动向 broker 发 PINGREQ，
broker 长时间收不到就断开连接。你**不需要写保活代码**，只要保持
`php bin/hyperf.php tcp-socket:mqtt-listen` 进程常驻（用 supervisor / systemd 守护）。

建议 `keep_alive` 设为 30–60 秒。

## 常见问题

| 现象 | 处理 |
| --- | --- |
| 连接被拒绝 (Connection refused) | broker 没启动，或 host/port 不对 |
| 一直收不到消息 | 检查 `subscriptions` 主题与发布端主题是否匹配（`+` 单层、`#` 多层） |
| 认证失败 | 填 `username` / `password`，或检查 broker 的 ACL |
| 提示 Configure `...handler...` | 配置文件没建，或 `handler` 不是你的 `AbstractMqttClient` 子类 |
| 进程退出 | MQTT 客户端是阻塞消费，退出即停止；用 supervisor/systemd 守护 |
