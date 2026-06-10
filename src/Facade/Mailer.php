<?php

/**
 * Mailer.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Facade;

use InitPHP\Mailer\Mailer as MailerInstance;

/**
 * Static facade over a single shared {@see MailerInstance}. Calls are forwarded
 * to that instance, so `Mailer::setTo(...)->...` reads like the object API
 * without having to hold a reference.
 *
 * @mixin MailerInstance
 *
 * @method static MailerInstance clear(bool $clearAttachments = false)
 * @method static MailerInstance setHeader(string $header, string $value)
 * @method static MailerInstance setFrom(string $from, string $name = '', ?string $returnPath = null)
 * @method static MailerInstance setReplyTo(string $replyTo, string $name = '')
 * @method static MailerInstance setTo(string|array<array-key, string> $to)
 * @method static MailerInstance setCC(string|array<array-key, string> $cc)
 * @method static MailerInstance setBCC(string|array<array-key, string> $bcc, ?int $limit = null)
 * @method static MailerInstance setSubject(string $subject)
 * @method static MailerInstance setMessage(string $body)
 * @method static MailerInstance attach(string $file, string $disposition = '', ?string $newName = null, ?string $mime = null)
 * @method static MailerInstance attachContent(string $content, string $name, string $disposition = 'attachment', ?string $mime = null)
 * @method static string setAttachmentCID(string $fileName)
 * @method static MailerInstance setAltMessage(string $str)
 * @method static MailerInstance setMailType(string $type = 'text')
 * @method static MailerInstance setWordWrap(bool $wordWrap = true)
 * @method static MailerInstance setProtocol(string $protocol = 'mail')
 * @method static MailerInstance setPriority(int $n = 3)
 * @method static MailerInstance setNewline(string $newLine)
 * @method static MailerInstance setCRLF(string $crlf)
 * @method static string getMessageID()
 * @method static bool validateEmail(array<array-key, string> $mails)
 * @method static bool isValidEmail(string $mail)
 * @method static string wordWrap(string $str, ?int $chars = null)
 * @method static void send(bool $autoClear = true)
 * @method static string printDebugger(list<string> $include = ['headers', 'subject', 'body'])
 * @method static array<string, mixed>|null getArchive()
 */
final class Mailer
{
    private static ?MailerInstance $instance = null;

    /**
     * Replaces the shared instance — for example one built with a specific
     * configuration. Pass null to reset it.
     */
    public static function setInstance(?MailerInstance $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        return self::instance()->{$name}(...$arguments);
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        return self::instance()->{$name}(...$arguments);
    }

    private static function instance(): MailerInstance
    {
        if (!self::$instance instanceof MailerInstance) {
            self::$instance = MailerInstance::newInstance();
        }

        return self::$instance;
    }
}
