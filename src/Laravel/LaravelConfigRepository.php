<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Laravel;

use Hk100\TcpSocket\Support\ConfigRepositoryInterface;
use Illuminate\Contracts\Config\Repository;

final class LaravelConfigRepository implements ConfigRepositoryInterface
{
    public function __construct(private readonly Repository $config)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config->get($key, $default);
    }

    public function all(): array
    {
        return $this->config->all();
    }
}
