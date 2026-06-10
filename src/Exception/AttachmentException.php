<?php

/**
 * AttachmentException.php
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
 * Thrown when an attachment cannot be read from disk or its MIME type cannot
 * be detected.
 */
final class AttachmentException extends MailerException
{
}
