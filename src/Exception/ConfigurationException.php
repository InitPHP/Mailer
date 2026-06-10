<?php

/**
 * ConfigurationException.php
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
 * Thrown when the mailer is asked to send a message it cannot build because
 * required configuration is missing — for example no sender, no recipient, or
 * an empty SMTP host.
 */
final class ConfigurationException extends MailerException
{
}
