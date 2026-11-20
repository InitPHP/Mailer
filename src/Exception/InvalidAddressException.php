<?php

/**
 * InvalidAddressException.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Exception;

/**
 * Thrown when an e-mail address fails validation while address validation is
 * enabled.
 */
final class InvalidAddressException extends MailerException
{
}
