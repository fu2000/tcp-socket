<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Hyperf;

use Hk100\TcpSocket\Support\ConfigRepositoryInterface;
use Hyperf\Contract\ConfigInterface;

final class HyperfConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private readonly ConfigInterface $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function all(): array
    {
        return method_exists($this->config, 'all') ? (array) $this->config->all() : [];
    }
}
