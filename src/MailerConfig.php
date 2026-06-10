<?php

/**
 * MailerConfig.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer;

use function strtolower;
use function strtoupper;

use const PHP_EOL;

/**
 * Immutable, normalised configuration built from the array passed to
 * {@see Mailer::__construct()}. Centralising the mapping here is what fixes the
 * 1.x bug where the constructor silently ignored the supplied configuration.
 *
 * Array keys keep their 1.x spelling (`SMTPHost`, `mailType`, `CRLF`, …) for
 * backward compatibility.
 */
final class MailerConfig
{
    /** Sender address used when {@see Mailer::setFrom()} is not called. */
    public readonly string $fromEmail;

    /** Sender display name used together with {@see $fromEmail}. */
    public readonly string $fromName;

    /** Value of the `User-Agent` and `X-Mailer` headers. */
    public readonly string $userAgent;

    /** Path to the sendmail binary (sendmail protocol only). */
    public readonly string $mailPath;

    /** Transport protocol: `mail`, `sendmail` or `smtp`. */
    public readonly string $protocol;

    public readonly string $smtpHost;

    public readonly string $smtpUser;

    public readonly string $smtpPass;

    public readonly int $smtpPort;

    /** SMTP connection timeout in seconds. */
    public readonly int $smtpTimeout;

    /** Whether to keep the SMTP connection open between messages. */
    public readonly bool $smtpKeepAlive;

    /** SMTP encryption: empty, `tls` or `ssl`. */
    public readonly string $smtpCrypto;

    /** Whether to perform SMTP authentication. */
    public readonly bool $smtpAuth;

    /** Whether plain-text bodies are word-wrapped. */
    public readonly bool $wordWrap;

    /** Column to word-wrap plain-text bodies at. */
    public readonly int $wrapChars;

    /** Message format: `text` or `html`. */
    public readonly string $mailType;

    /** Character set, upper-cased (e.g. `UTF-8`). */
    public readonly string $charset;

    /** Alternative plain-text body for HTML messages. */
    public readonly string $altMessage;

    /** Whether addresses are validated as they are added. */
    public readonly bool $validate;

    /** X-Priority value, 1 (highest) to 5 (lowest). */
    public readonly int $priority;

    /** Header line ending. */
    public readonly string $newline;

    /** Body line ending used by the quoted-printable encoder. */
    public readonly string $crlf;

    /** Whether to request Delivery Status Notifications (SMTP). */
    public readonly bool $dsn;

    /** Whether to send a multipart/alternative body for HTML messages. */
    public readonly bool $sendMultipart;

    /** Whether to split large BCC lists into batches. */
    public readonly bool $bccBatchMode;

    /** Maximum number of BCC recipients per batch. */
    public readonly int $bccBatchSize;

    private const NEWLINES = ["\n", "\r\n", "\n\r", "\r"];

    private const PROTOCOLS = ['mail', 'sendmail', 'smtp'];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->fromEmail      = $this->str($config, 'fromEmail', '');
        $this->fromName       = $this->str($config, 'fromName', '');
        $this->userAgent      = $this->str($config, 'userAgent', 'InitPHP Mailer');
        $this->mailPath       = $this->str($config, 'mailPath', '/usr/sbin/sendmail');
        $this->smtpHost       = $this->str($config, 'SMTPHost', '');
        $this->smtpUser       = $this->str($config, 'SMTPUser', '');
        $this->smtpPass       = $this->str($config, 'SMTPPass', '');
        $this->smtpPort       = $this->int($config, 'SMTPPort', 25);
        $this->smtpTimeout    = $this->int($config, 'SMTPTimeout', 5);
        $this->smtpKeepAlive  = $this->bool($config, 'SMTPKeepAlive', false);
        $this->altMessage     = $this->str($config, 'altMessage', '');
        $this->validate       = $this->bool($config, 'validate', true);
        $this->wordWrap       = $this->bool($config, 'wordWrap', true);
        $this->wrapChars      = $this->int($config, 'wrapChars', 76);
        $this->dsn            = $this->bool($config, 'DSN', false);
        $this->sendMultipart  = $this->bool($config, 'sendMultipart', true);
        $this->bccBatchMode   = $this->bool($config, 'BCCBatchMode', false);
        $this->bccBatchSize   = $this->int($config, 'BCCBatchSize', 200);

        $crypto = strtolower($this->str($config, 'SMTPCrypto', ''));
        $this->smtpCrypto = \in_array($crypto, ['tls', 'ssl'], true) ? $crypto : '';

        $this->smtpAuth = $this->bool(
            $config,
            'SMTPAuth',
            $this->smtpUser !== '' && $this->smtpPass !== '',
        );

        $protocol = strtolower($this->str($config, 'protocol', 'mail'));
        $this->protocol = \in_array($protocol, self::PROTOCOLS, true) ? $protocol : 'mail';

        $this->mailType = strtolower($this->str($config, 'mailType', 'text')) === 'html' ? 'html' : 'text';

        // The charset is upper-cased here so the QEncoder's `=== 'UTF-8'` fast
        // path is reached; leaving it lower-cased was a 1.x bug.
        $this->charset = strtoupper($this->str($config, 'charset', 'UTF-8'));

        $priority = $this->int($config, 'priority', 3);
        $this->priority = ($priority >= 1 && $priority <= 5) ? $priority : 3;

        $newline = $this->str($config, 'newline', PHP_EOL);
        $this->newline = \in_array($newline, self::NEWLINES, true) ? $newline : PHP_EOL;

        $crlf = $this->str($config, 'CRLF', "\n");
        $this->crlf = \in_array($crlf, self::NEWLINES, true) ? $crlf : "\n";
    }

    /**
     * @param array<string, mixed> $config
     */
    private function str(array $config, string $key, string $default): string
    {
        return isset($config[$key]) && (\is_string($config[$key]) || is_numeric($config[$key]))
            ? (string) $config[$key]
            : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function int(array $config, string $key, int $default): int
    {
        return isset($config[$key]) && is_numeric($config[$key]) ? (int) $config[$key] : $default;
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bool(array $config, string $key, bool $default): bool
    {
        return isset($config[$key]) ? (bool) $config[$key] : $default;
    }
}
