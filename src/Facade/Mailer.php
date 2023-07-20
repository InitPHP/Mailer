<?php
/**
 * Mailer.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.1
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Facade;

use \InitPHP\Mailer\Mailer as MailerInstance;

/**
 * @mixin MailerInstance
 * @method static MailerInstance clear(bool $clearAttachments = false)
 * @method static MailerInstance setHeader(string $header, string $value)
 * @method static MailerInstance setFrom(string $from, string $name = '', null|string $returnPath = null)
 * @method static MailerInstance setReplyTo(string $replyTo, string $name = '')
 * @method static MailerInstance setTo(string[]|string $to)
 * @method static MailerInstance setCC(string $cc)
 * @method static MailerInstance setBCC(string $bcc, null|int $limit = null)
 * @method static MailerInstance setSubject(string $subject)
 * @method static MailerInstance setMessage(string $body)
 * @method static MailerInstance setAttachmentCID(string $fileName)
 * @method static MailerInstance setAltMessage(string $str)
 * @method static MailerInstance setMailType(string $type = 'text')
 * @method static MailerInstance setWordWrap(bool $wordWrap = true)
 * @method static MailerInstance setProtocol(string $protocol = 'mail')
 * @method static MailerInstance setPriority(int $n = 3)
 * @method static MailerInstance setNewline(string $newLine = PHP_EOL)
 * @method static MailerInstance setCRLF(string $CRLF = PHP_EOL)
 * @method static MailerInstance|false attach(string $file, string $disposition = '', null|string $newName = null, null|string $mime = null)
 * @method static string getMessageID()
 * @method static bool validateEmail(string[] $mails)
 * @method static bool isValidEmail(string $mail)
 * @method static string|string[] cleanEmail(string|string[] $mail)
 * @method static string wordWrap(string $str, null|int $chars = null)
 * @method static bool send(bool $autoClear = true)
 * @method static void batchBCCSend()
 * @method static string printDebugger(array $include = ['headers', 'subject', 'body'])
 */
class Mailer
{

    protected static MailerInstance $instance;

    public function __call($name, $arguments)
    {
        return self::__getInstance()->{$name}(...$arguments);
    }

    public static function __callStatic($name, $arguments)
    {
        return self::__getInstance()->{$name}(...$arguments);
    }

    protected static function __getInstance(): MailerInstance
    {
        if (!isset(self::$instance)) {
            self::$instance = MailerInstance::newInstance();
        }

        return self::$instance;
    }

}
