<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Hyperf\Command;

use Hk100\TcpSocket\Contracts\WebSocketServerHandlerInterface;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Hyperf\Command\Command;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Console\Input\InputOption;

final class WebSocketServerCommand extends Command
{
    protected $name = 'tcp-socket:ws-serve';

    protected $description = 'Start the WebSocket server';

    protected $coroutine = false;

    protected function configure(): void
    {
        parent::configure();
        $this->addOption('driver', null, InputOption::VALUE_OPTIONAL, 'auto|swoole|react');
        $this->addOption('host', null, InputOption::VALUE_OPTIONAL, 'Override the listen host');
        $this->addOption('port', null, InputOption::VALUE_OPTIONAL, 'Override the listen port');
    }

    public function handle(): int
    {
        $container = ApplicationContext::getContainer();
        $config = (array) $container->get(ConfigInterface::class)->get('tcp-socket.websocket.server', []);

        $host = $this->input->getOption('host');
        if (is_string($host)) {
            $config['host'] = $host;
        }
        $port = $this->input->getOption('port');
        if ($port !== null) {
            $config['port'] = (int) $port;
        }

        $class = $config['handler'] ?? null;
        if (!is_string($class) || $class === '') {
            $this->output->writeln('<error>Configure tcp-socket.websocket.server.handler with a class extending AbstractWebSocketServerHandler.</error>');
            return 1;
        }

        $handler = $container->get($class);
        if (!$handler instanceof WebSocketServerHandlerInterface) {
            $this->output->writeln("<error>Configured handler {$class} must extend AbstractWebSocketServerHandler.</error>");
            return 1;
        }

        $driver = $this->input->getOption('driver');

        $container->get(ServerRunner::class)->runWebSocket(
            ServerConfig::forWebSocket($config),
            $handler,
            $this->output,
            is_string($driver) ? $driver : null
        );

        return 0;
    }
}
