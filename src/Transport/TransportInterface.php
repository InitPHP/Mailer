<?php

/**
 * TransportInterface.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Transport;

use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\PreparedEmail;

/**
 * A delivery backend. Implementations hand a prepared message to the outside
 * world (the `mail()` function, a sendmail pipe, or an SMTP socket) and throw
 * on failure rather than returning a status.
 */
interface TransportInterface
{
    /**
     * @throws TransportException When the message could not be handed off.
     */
    public function send(PreparedEmail $email): void;
}
