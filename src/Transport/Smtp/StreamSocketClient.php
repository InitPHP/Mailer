<?php

/**
 * StreamSocketClient.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Transport\Smtp;

use InitPHP\Mailer\Exception\TransportException;

use function fclose;
use function fgets;
use function fsockopen;
use function fwrite;
use function stream_set_timeout;
use function stream_socket_enable_crypto;
use function substr;
use function time;
use function usleep;

/**
 * Default {@see SocketClientInterface}, backed by a real `fsockopen()` stream.
 * The write loop tolerates partial and zero-length writes up to the configured
 * timeout.
 */
final class StreamSocketClient implements SocketClientInterface
{
    /** @var resource|null */
    private $socket;

    private int $timeout = 5;

    public function open(string $host, int $port, int $timeout): void
    {
        $errno = 0;
        $errstr = '';
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new TransportException(
                \sprintf('Could not connect to SMTP host "%s:%d" (%d: %s).', $host, $port, $errno, $errstr),
            );
        }
        stream_set_timeout($socket, $timeout);
        $this->socket = $socket;
        $this->timeout = $timeout;
    }

    public function isOpen(): bool
    {
        return \is_resource($this->socket);
    }

    public function write(string $bytes): void
    {
        if (!\is_resource($this->socket)) {
            throw new TransportException('The SMTP socket is not open.');
        }
        $length = \strlen($bytes);
        if ($length === 0) {
            return;
        }

        $result = null;
        for ($written = 0, $timestamp = 0; $written < $length; $written += $result) {
            $result = @fwrite($this->socket, substr($bytes, $written));
            if ($result === false) {
                break;
            }
            if ($result === 0) {
                if ($timestamp === 0) {
                    $timestamp = time();
                } elseif ($timestamp < (time() - $this->timeout)) {
                    $result = false;
                    break;
                }
                usleep(250000);
                continue;
            }
            $timestamp = 0;
        }

        if (!\is_int($result)) {
            throw new TransportException('Failed to write to the SMTP socket.');
        }
    }

    public function readLine(int $length = 512): string|false
    {
        if (!\is_resource($this->socket)) {
            return false;
        }

        return fgets($this->socket, max(1, $length));
    }

    public function enableCrypto(int $cryptoMethod): bool
    {
        return \is_resource($this->socket)
            && stream_socket_enable_crypto($this->socket, true, $cryptoMethod) === true;
    }

    public function close(): void
    {
        if (\is_resource($this->socket)) {
            fclose($this->socket);
            $this->socket = null;
        }
    }
}
