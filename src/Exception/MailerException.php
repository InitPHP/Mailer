<?php

/**
 * MailerException.php
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

use RuntimeException;

/**
 * Base type for every exception thrown by the library. Catch this to handle
 * any mailer failure regardless of its specific cause.
 */
class MailerException extends RuntimeException
{
}
