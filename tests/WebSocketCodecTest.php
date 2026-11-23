<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests;

use Hk100\TcpSocket\WebSocket\FrameEncoder;
use Hk100\TcpSocket\WebSocket\FrameParser;
use Hk100\TcpSocket\WebSocket\HandshakeClient;
use Hk100\TcpSocket\WebSocket\HandshakeServer;
use Hk100\TcpSocket\WebSocket\Opcode;
use Hk100\TcpSocket\WebSocket\WebSocketException;
use PHPUnit\Framework\TestCase;

final class WebSocketCodecTest extends TestCase
{
    public function testClientToServerMaskedRoundTrip(): void
    {
        $parser = new FrameParser(requireMask: true);
        $wire = FrameEncoder::encode('hello', Opcode::Text, mask: true);

        $frames = $parser->push($wire);

        self::assertCount(1, $frames);
        self::assertSame('hello', $frames[0]->payload);
        self::assertSame(Opcode::Text, $frames[0]->opcode);
        self::assertFalse($frames[0]->isBinary());
    }

    public function testServerToClientUnmaskedRoundTrip(): void
    {
        $parser = new FrameParser(requireMask: false);
        $wire = FrameEncoder::binary("\x00\x01\x02", mask: false);

        $frames = $parser->push($wire);

        self::assertCount(1, $frames);
        self::assertSame("\x00\x01\x02", $frames[0]->payload);
        self::assertTrue($frames[0]->isBinary());
    }

    public function testFragmentedDataFramesAreReassembled(): void
    {
        $parser = new FrameParser(requireMask: true);
        $wire = FrameEncoder::encode('Hel', Opcode::Text, mask: true, fin: false)
            . FrameEncoder::encode('lo', Opcode::Continuation, mask: true, fin: true);

        $frames = $parser->push($wire);

        self::assertCount(1, $frames);
        self::assertSame('Hello', $frames[0]->payload);
    }

    public function testMediumPayloadUsesExtendedLengthHeader(): void
    {
        $parser = new FrameParser(requireMask: true);
        $payload = str_repeat('a', 300);

        $frames = $parser->push(FrameEncoder::encode($payload, Opcode::Text, mask: true));

        self::assertCount(1, $frames);
        self::assertSame($payload, $frames[0]->payload);
    }

    public function testServerRejectsUnmaskedClientFrame(): void
    {
        $parser = new FrameParser(requireMask: true);
        $wire = FrameEncoder::encode('bad', Opcode::Text, mask: false);

        $this->expectException(WebSocketException::class);
        $parser->push($wire);
    }

    public function testServerHandshakeAcceptsAValidClientRequest(): void
    {
        $key = HandshakeClient::generateKey();
        $request = HandshakeClient::buildRequest('127.0.0.1', 9502, '/chat', $key);

        $response = HandshakeServer::buildAcceptResponse($request);

        self::assertNotNull($response);
        self::assertStringContainsString('101 Switching Protocols', $response);
        self::assertTrue(HandshakeClient::verifyResponse($response, $key));
        self::assertSame('/chat', HandshakeServer::getPath($request));
    }

    public function testClientRejectsResponseForADifferentKey(): void
    {
        $key = HandshakeClient::generateKey();
        $request = HandshakeClient::buildRequest('127.0.0.1', 9502, '/', $key);
        $response = HandshakeServer::buildAcceptResponse($request);

        self::assertNotNull($response);
        self::assertFalse(HandshakeClient::verifyResponse($response, HandshakeClient::generateKey()));
    }
}
