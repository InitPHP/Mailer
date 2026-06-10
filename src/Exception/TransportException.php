<?php

/**
 * TransportException.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Exception;

/**
 * Thrown when a transport fails to hand the message off — a refused SMTP
 * connection, an unexpected SMTP reply code, a sendmail non-zero exit status,
 * or `mail()` returning false. For SMTP failures the exception code carries the
 * server reply code when one is available.
 */
final class TransportException extends MailerException
{
}
