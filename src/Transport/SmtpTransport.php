<?php

/**
 * SmtpTransport.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Transport;

use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\HeaderRenderer;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\Smtp\SocketClientInterface;
use InitPHP\Mailer\Validation\EmailValidator;
use Throwable;

use function array_merge;
use function base64_encode;
use function implode;
use function preg_replace;
use function preg_replace_callback;
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
    ) {}

    public function send(PreparedEmail $email): void
    {
        if (trim($this->host) === '') {
            throw new ConfigurationException('No SMTP host has been configured.');
        }

        // Everything that is written to the wire is checked, and the message is
        // rendered, before a connection is opened: a bad recipient, hostname or
        // header never reaches the socket, and never leaves a half-open
        // connection behind. The sender is an Address, which already refuses
        // unsafe characters.
        EmailValidator::assertSafeAddresses([...$email->to, ...$email->cc, ...$email->bcc]);
        if (EmailValidator::hasLineBreakOrNul($this->hostname)) {
            throw new TransportException('The SMTP client hostname must not contain a line break or a NUL character.');
        }

        // A custom Subject gives way to the real one. To is written as it always
        // was: it replaces a custom header named exactly "To" and sits beside any
        // other spelling.
        $headers = HeaderRenderer::without($email->headers, ['Subject']);
        $headers['To'] = implode(', ', $email->to);
        $headers['Subject'] = $email->subject;
        $message = HeaderRenderer::render($headers, $this->newline)
            . $email->contentHeaders . $this->newline . $this->newline . $email->body;
        // Dot-stuffing: any line starting with "." must be escaped to "..". A line
        // starts at the beginning of the message and after any CR or LF, because
        // the configured newline may be a bare "\r" or "\n\r".
        $message = (string) preg_replace('/(?<![^\r\n])\./', '..', $message);

        try {
            $this->connect();
            $this->authenticate();

            $this->command('MAIL FROM:<' . $email->from->getEmail() . '>', 250);
            foreach (array_merge($email->to, $email->cc, $email->bcc) as $recipient) {
                if ($recipient !== '') {
                    $this->command($this->rcpt($recipient), 250);
                }
            }
            $this->command('DATA', 354);

            $this->client->write($message . $this->newline . '.' . $this->newline);
            $reply = $this->readReply();
            if (strncmp($reply, '250', 3) !== 0) {
                throw new TransportException(
                    'The SMTP server rejected the message: ' . trim($reply),
                    $this->code($reply),
                );
            }

            $this->finish();
        } catch (Throwable $e) {
            // The state of the conversation is unknown. Drop the connection so
            // the next send() starts from a fresh greeting, EHLO, STARTTLS and
            // AUTH instead of reusing a socket that never finished (or failed)
            // its TLS upgrade and sending the credentials in clear text.
            $this->disconnect();

            throw $e;
        }
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
        $greeting = $this->readReply();
        if (strncmp($greeting, '220', 3) !== 0) {
            throw new TransportException(
                'The SMTP server did not greet the connection: ' . trim($greeting),
                $this->code($greeting),
            );
        }

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
        $this->disconnect();
    }

    /**
     * Closes the connection and forgets that it was authenticated.
     */
    private function disconnect(): void
    {
        $this->authenticated = false;
        if ($this->client->isOpen()) {
            $this->client->close();
        }
    }

    private function hello(): string
    {
        return ($this->extendedHello ? 'EHLO ' : 'HELO ') . $this->hostname;
    }

    private function rcpt(string $address): string
    {
        if ($this->dsn) {
            return 'RCPT TO:<' . $address . '> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;' . $this->xtext($address);
        }

        return 'RCPT TO:<' . $address . '>';
    }

    /**
     * Encodes an address as RFC 3461 `xtext` for the `ORCPT` parameter: every
     * byte outside `!`..`~`, and `+` and `=` themselves, becomes `+XX`, so a
     * space or another delimiter in an address cannot start a new parameter.
     */
    private function xtext(string $value): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x2A\x2C-\x3C\x3E-\x7E]/',
            static fn(array $match): string => \sprintf('+%02X', \ord($match[0])),
            $value,
        );
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
