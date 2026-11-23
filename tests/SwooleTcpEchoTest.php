<?php

declare(strict_types=1);

namespace Hk100\TcpSocket\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Spawns the standalone Swoole echo server in a child process and talks to it
 * over a raw TCP socket.
 *
 * Skipped unless HK100_RUN_SWOOLE_TESTS=1 (forking a real Swoole server inside
 * the test process is unsafe, so it must run out-of-process).
 */
final class SwooleTcpEchoTest extends TestCase
{
    public function testEchoOverSwooleTcp(): void
    {
        if (!extension_loaded('swoole') && !extension_loaded('openswoole')) {
            self::markTestSkipped('ext-swoole is not installed.');
        }
        if (getenv('HK100_RUN_SWOOLE_TESTS') !== '1') {
            self::markTestSkipped('Set HK100_RUN_SWOOLE_TESTS=1 to run the Swoole integration test.');
        }

        $port = $this->freePort();
        $script = dirname(__DIR__) . '/examples/standalone/swoole-tcp-echo.php';

        $process = proc_open(
            [PHP_BINARY, $script, '127.0.0.1', (string) $port],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes
        );

        self::assertIsResource($process);

        try {
            $client = $this->connect($port);
            self::assertNotNull($client, 'Swoole server did not start in time.');

            fwrite($client, "hello\n");
            $reply = fgets($client);
            fclose($client);

            self::assertSame('echo: hello', trim((string) $reply));
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            proc_terminate($process, 15);
            proc_close($process);
        }
    }

    private function connect(int $port): mixed
    {
        $deadline = microtime(true) + 8.0;

        while (microtime(true) < $deadline) {
            $client = @stream_socket_client("tcp://127.0.0.1:{$port}", $errno, $errstr, 0.2);
            if ($client !== false) {
                return $client;
            }
            usleep(100_000);
        }

        return null;
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            self::markTestSkipped("Cannot allocate a port: {$errstr}");
        }

        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
