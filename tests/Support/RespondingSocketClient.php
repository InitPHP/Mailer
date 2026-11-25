<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Support;

use InitPHP\Mailer\Transport\Smtp\SocketClientInterface;

use function implode;
use function str_starts_with;

/**
 * An in-memory {@see SocketClientInterface} that answers every command with a
 * success reply (`220` greeting, `354` for DATA, `221` for QUIT, `250` for the
 * rest), so a test can send several messages without scripting each reply.
 */
final class RespondingSocketClient implements SocketClientInterface
{
    /** @var list<string> */
    public array $writes = [];

    private bool $open = false;

    private string $next = "220 hi\r\n";

    public function open(string $host, int $port, int $timeout): void
    {
        $this->open = true;
        $this->next = "220 hi\r\n";
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function write(string $bytes): void
    {
        $this->writes[] = $bytes;
        $this->next = match (true) {
            str_starts_with($bytes, 'DATA') => "354 go\r\n",
            str_starts_with($bytes, 'QUIT') => "221 bye\r\n",
            default                         => "250 ok\r\n",
        };
    }

    public function readLine(int $length = 512): string
    {
        return $this->next;
    }

    public function enableCrypto(int $cryptoMethod): bool
    {
        return true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    /**
     * Everything written, as one string per connection (split after each QUIT).
     *
     * @return list<string>
     */
    public function conversations(): array
    {
        $all = implode('', $this->writes);
        $parts = [];
        foreach (\explode("QUIT\n", $all) as $part) {
            if ($part !== '') {
                $parts[] = $part;
            }
        }

        return $parts;
    }
}
