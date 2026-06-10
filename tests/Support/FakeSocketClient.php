<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Support;

use InitPHP\Mailer\Transport\Smtp\SocketClientInterface;

use function array_shift;
use function implode;

/**
 * In-memory {@see SocketClientInterface} for SMTP tests. It replays a scripted
 * list of server reply lines and records every command written, so the SMTP
 * conversation can be asserted without a real server.
 */
final class FakeSocketClient implements SocketClientInterface
{
    /** @var list<string> Commands written to the socket. */
    public array $writes = [];

    public bool $cryptoEnabled = false;

    public ?string $openedHost = null;

    public int $openCount = 0;

    public int $closeCount = 0;

    private bool $open = false;

    /** @var list<string> */
    private array $replies;

    /**
     * @param list<string> $replies Reply lines, in the order the server sends
     *                              them (one entry per `readLine()` call).
     */
    public function __construct(array $replies)
    {
        $this->replies = $replies;
    }

    public function open(string $host, int $port, int $timeout): void
    {
        $this->open = true;
        $this->openedHost = $host;
        ++$this->openCount;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function write(string $bytes): void
    {
        $this->writes[] = $bytes;
    }

    public function readLine(int $length = 512): string|false
    {
        if ($this->replies === []) {
            return false;
        }

        return array_shift($this->replies);
    }

    public function enableCrypto(int $cryptoMethod): bool
    {
        $this->cryptoEnabled = true;

        return true;
    }

    public function close(): void
    {
        $this->open = false;
        ++$this->closeCount;
    }

    /**
     * All commands written, concatenated — handy for substring assertions.
     */
    public function conversation(): string
    {
        return implode('', $this->writes);
    }
}
