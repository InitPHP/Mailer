<?php

/**
 * SmtpTransport.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Transport;

use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\HeaderRenderer;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\Smtp\SocketClientInterface;
use Throwable;

use function array_merge;
use function base64_encode;
use function implode;
use function preg_replace;
use function strncmp;
use function strstr;
use function substr;
use function trim;

use const STREAM_CRYPTO_METHOD_TLS_CLIENT;

/**
 * Speaks SMTP over a {@see SocketClientInterface}. Supports STARTTLS / implicit
 * TLS, `AUTH LOGIN`, DSN and persistent (keep-alive) connections.
 */
final class SmtpTransport implements TransportInterface
{
    private bool $authenticated = false;

    public function __construct(
        private readonly SocketClientInterface $client,
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeout,
        private readonly string $crypto,
        private readonly bool $auth,
        private readonly string $user,
        private readonly string $pass,
        private readonly bool $keepAlive,
        private readonly bool $dsn,
        private readonly bool $extendedHello,
        private readonly string $hostname,
        private readonly string $newline,
    ) {
    }

    public function send(PreparedEmail $email): void
    {
        if (trim($this->host) === '') {
            throw new ConfigurationException('No SMTP host has been configured.');
        }

        $this->connect();
        $this->authenticate();

        $this->command('MAIL FROM:<' . $email->from->getEmail() . '>', 250);
        foreach (array_merge($email->to, $email->cc, $email->bcc) as $recipient) {
            if ($recipient !== '') {
                $this->command($this->rcpt($recipient), 250);
            }
        }
        $this->command('DATA', 354);

        $headers = $email->headers;
        $headers['To'] = implode(', ', $email->to);
        $headers['Subject'] = $email->subject;
        $message = HeaderRenderer::render($headers, $this->newline)
            . $email->contentHeaders . $this->newline . $this->newline . $email->body;
        // Dot-stuffing: any line starting with "." must be escaped to "..".
        $message = (string) preg_replace('/^\./m', '..', $message);

        $this->client->write($message . $this->newline . '.' . $this->newline);
        $reply = $this->readReply();
        if (strncmp($reply, '250', 3) !== 0) {
            throw new TransportException('The SMTP server rejected the message: ' . trim($reply), $this->code($reply));
        }

        $this->finish();
    }

    public function __destruct()
    {
        if (!$this->client->isOpen()) {
            return;
        }
        try {
            $this->client->write('QUIT' . $this->newline);
        } catch (Throwable) {
            // Best-effort; the connection is being torn down anyway.
        }
        $this->client->close();
    }

    private function connect(): void
    {
        if ($this->client->isOpen()) {
            return;
        }

        $prefix = '';
        if ($this->port === 465) {
            $prefix = 'tls://';
        } elseif ($this->crypto === 'ssl') {
            $prefix = 'ssl://';
        }

        $this->client->open($prefix . $this->host, $this->port, $this->timeout);
        $this->readReply();

        if ($this->crypto === 'tls') {
            $this->command($this->hello(), 250);
            $this->command('STARTTLS', 220);
            if (!$this->client->enableCrypto(STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new TransportException('Failed to enable TLS encryption on the SMTP connection.');
            }
        }

        $this->command($this->hello(), 250);
    }

    private function authenticate(): void
    {
        if (!$this->auth || ($this->keepAlive && $this->authenticated)) {
            return;
        }
        if ($this->user === '' && $this->pass === '') {
            throw new ConfigurationException('SMTP authentication requires a username and a password.');
        }

        $this->client->write('AUTH LOGIN' . $this->newline);
        $reply = $this->readReply();
        if (strncmp($reply, '503', 3) === 0) {
            $this->authenticated = true;

            return; // Already authenticated on this connection.
        }
        if (strncmp($reply, '334', 3) !== 0) {
            throw new TransportException('SMTP AUTH LOGIN was rejected: ' . trim($reply), $this->code($reply));
        }

        $this->command(base64_encode($this->user), 334);

        $this->client->write(base64_encode($this->pass) . $this->newline);
        $reply = $this->readReply();
        if (strncmp($reply, '235', 3) !== 0) {
            throw new TransportException('SMTP authentication failed: ' . trim($reply), $this->code($reply));
        }

        $this->authenticated = true;
    }

    private function finish(): void
    {
        if ($this->keepAlive) {
            $this->command('RSET', 250);

            return;
        }
        $this->client->write('QUIT' . $this->newline);
        $this->readReply();
        $this->client->close();
    }

    private function hello(): string
    {
        return ($this->extendedHello ? 'EHLO ' : 'HELO ') . $this->hostname;
    }

    private function rcpt(string $address): string
    {
        if ($this->dsn) {
            return 'RCPT TO:<' . $address . '> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;' . $address;
        }

        return 'RCPT TO:<' . $address . '>';
    }

    private function command(string $command, int $expected): string
    {
        $this->client->write($command . $this->newline);
        $reply = $this->readReply();
        if ($this->code($reply) !== $expected) {
            $verb = strstr($command, ' ', true);
            throw new TransportException(
                \sprintf(
                    'SMTP command "%s" failed (expected %d): %s',
                    $verb === false ? $command : $verb,
                    $expected,
                    trim($reply),
                ),
                $this->code($reply),
            );
        }

        return $reply;
    }

    private function readReply(): string
    {
        $data = '';
        while (($line = $this->client->readLine(512)) !== false) {
            $data .= $line;
            if (\strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }

        return $data;
    }

    private function code(string $reply): int
    {
        return (int) substr($reply, 0, 3);
    }
}
