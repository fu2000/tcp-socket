<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Hyperf\Command;

use Hk100\TcpSocket\Contracts\TcpServerHandlerInterface;
use Hk100\TcpSocket\Server\ServerConfig;
use Hk100\TcpSocket\Server\ServerRunner;
use Hyperf\Command\Command;
use Hyperf\Context\ApplicationContext;
use Hyperf\Contract\ConfigInterface;
use Symfony\Component\Console\Input\InputOption;

final class TcpServerCommand extends Command
{
    protected ?string $name = 'tcp-socket:serve';

    protected string $description = 'Start the TCP socket server';

    /**
     * Run outside a coroutine so Swoole\Server (or a blocking React loop) can
     * be created; otherwise "eventLoop has already been created".
     */
    protected bool $coroutine = false;

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
        $config = (array) $container->get(ConfigInterface::class)->get('tcp-socket.tcp.server', []);

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
            $this->output->writeln('<error>Configure tcp-socket.tcp.server.handler with a class extending AbstractTcpServerHandler.</error>');
            return 1;
        }
        if (!class_exists($class)) {
            $this->output->writeln("<error>Handler class {$class} does not exist. Create it and update tcp-socket.tcp.server.handler in config/autoload/tcp-socket.php.</error>");
            return 1;
        }

        $handler = $container->get($class);
        if (!$handler instanceof TcpServerHandlerInterface) {
            $this->output->writeln("<error>Configured handler {$class} must extend AbstractTcpServerHandler.</error>");
            return 1;
        }

        $driver = $this->input->getOption('driver');

        $container->get(ServerRunner::class)->runTcp(
            ServerConfig::forTcp($config),
            $handler,
            $this->output,
            is_string($driver) ? $driver : null
        );

        return 0;
    }
}
