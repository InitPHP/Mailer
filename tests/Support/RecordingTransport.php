<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Support;

use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\TransportInterface;
use RuntimeException;

use function array_key_last;

/**
 * A transport that records every {@see PreparedEmail} handed to it instead of
 * sending it, so the orchestration in {@see \InitPHP\Mailer\Mailer} can be
 * asserted without touching the network.
 */
final class RecordingTransport implements TransportInterface
{
    /** @var list<PreparedEmail> */
    public array $sent = [];

    public function send(PreparedEmail $email): void
    {
        $this->sent[] = $email;
    }

    public function last(): PreparedEmail
    {
        $key = array_key_last($this->sent);
        if ($key === null) {
            throw new RuntimeException('No message has been recorded yet.');
        }

        return $this->sent[$key];
    }
}
