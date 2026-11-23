<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Framing;

/**
 * Builds a fresh, stateful framing instance from a config name.
 */
final class FramingFactory
{
    /**
     * @param array{delimiter?: string, length_bytes?: int} $options
     */
    public static function make(string $name, array $options = []): FramingInterface
    {
        return match (strtolower($name)) {
            '', 'raw', 'none' => new RawFraming(),
            'eof', 'delimiter' => new DelimiterFraming((string) ($options['delimiter'] ?? "\n")),
            'length', 'length-prefix' => new LengthPrefixFraming((int) ($options['length_bytes'] ?? 4)),
            default => throw new \InvalidArgumentException("Unknown framing type [{$name}]."),
        };
    }
}
