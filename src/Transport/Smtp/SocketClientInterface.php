<?php

/**
 * SocketClientInterface.php
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

/**
 * The raw byte-level socket {@see \InitPHP\Mailer\Transport\SmtpTransport}
 * talks over. Abstracting it lets the SMTP conversation be unit-tested against
 * an in-memory fake instead of a live server.
 */
interface SocketClientInterface
{
    /**
     * Opens the connection.
     *
     * @throws TransportException When the connection cannot be established.
     */
    public function open(string $host, int $port, int $timeout): void;

    public function isOpen(): bool;

    /**
     * Writes raw bytes to the socket.
     *
     * @throws TransportException When the bytes cannot be written.
     */
    public function write(string $bytes): void;

    /**
     * Reads up to one line (`$length` bytes max). Returns false at end of
     * stream.
     */
    public function readLine(int $length = 512): string|false;

    /**
     * Enables TLS on the open connection.
     */
    public function enableCrypto(int $cryptoMethod): bool;

    public function close(): void;
}
