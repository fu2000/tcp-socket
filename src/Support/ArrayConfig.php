<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Support;

/**
 * Plain-array config source with dot-notation lookups.
 */
final class ArrayConfig implements ConfigRepositoryInterface
{
    /**
     * @param array<string, mixed> $items
     */
    public function __construct(private readonly array $items = [])
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if ($key === '') {
            return $this->items;
        }

        $value = $this->items;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function all(): array
    {
        return $this->items;
    }
}
