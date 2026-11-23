<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests;

use Hk100\TcpSocket\Framing\DelimiterFraming;
use Hk100\TcpSocket\Framing\FramingFactory;
use Hk100\TcpSocket\Framing\LengthPrefixFraming;
use Hk100\TcpSocket\Framing\RawFraming;
use PHPUnit\Framework\TestCase;

final class FramingTest extends TestCase
{
    public function testRawFramingTreatsEachChunkAsAMessage(): void
    {
        $framing = new RawFraming();

        self::assertSame(['abc'], $framing->push('abc'));
        self::assertSame([], $framing->push(''));
        self::assertSame('xyz', $framing->encode('xyz'));
    }

    public function testDelimiterFramingBuffersPartialMessages(): void
    {
        $framing = new DelimiterFraming("\n");

        self::assertSame([], $framing->push('he'));
        self::assertSame(['hello'], $framing->push("llo\n"));
        self::assertSame(['a', 'b'], $framing->push("a\nb\n"));
        self::assertSame("x\n", $framing->encode('x'));
    }

    public function testLengthPrefixFramingWaitsForTheFullPayload(): void
    {
        $framing = new LengthPrefixFraming(4);
        $wire = $framing->encode('hello');

        self::assertSame(pack('N', 5) . 'hello', $wire);
        self::assertSame([], $framing->push(substr($wire, 0, 3)));
        self::assertSame(['hello'], $framing->push(substr($wire, 3)));
    }

    public function testLengthPrefixFramingHandlesMultipleMessages(): void
    {
        $framing = new LengthPrefixFraming(2);
        $wire = $framing->encode('ab') . $framing->encode('cde');

        self::assertSame(['ab', 'cde'], $framing->push($wire));
    }

    public function testFactoryBuildsKnownFramings(): void
    {
        self::assertInstanceOf(RawFraming::class, FramingFactory::make('raw'));
        self::assertInstanceOf(DelimiterFraming::class, FramingFactory::make('eof', ['delimiter' => "\r\n"]));
        self::assertInstanceOf(LengthPrefixFraming::class, FramingFactory::make('length', ['length_bytes' => 2]));
    }

    public function testFactoryRejectsUnknownFraming(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        FramingFactory::make('does-not-exist');
    }
}
