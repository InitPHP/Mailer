<?php

/**
 * EmailValidator.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Validation;

use InitPHP\Mailer\Exception\InvalidAddressException;

use function filter_var;
use function preg_match;

use const FILTER_VALIDATE_EMAIL;

/**
 * Validates e-mail addresses. `isValid()`/`isShellSafe()` answer questions;
 * the `assert*()` helpers throw {@see InvalidAddressException} on failure.
 */
final class EmailValidator
{
    /**
     * Whether the address passes PHP's e-mail filter.
     */
    public function isValid(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Throws when the address is not valid.
     *
     * @throws InvalidAddressException
     */
    public function assertValid(string $email): void
    {
        if (!$this->isValid($email)) {
            throw new InvalidAddressException(\sprintf('"%s" is not a valid e-mail address.', $email));
        }
    }

    /**
     * Throws when any address in the list is not valid.
     *
     * @param iterable<string> $emails
     *
     * @throws InvalidAddressException
     */
    public function assertAllValid(iterable $emails): void
    {
        foreach ($emails as $email) {
            $this->assertValid($email);
        }
    }

    /**
     * Whether the address is safe to pass to a shell as a `-f` envelope sender.
     * It must both pass the e-mail filter unchanged and match a conservative
     * local-part/domain pattern, so no shell metacharacters can slip through.
     */
    public function isShellSafe(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) === $email
            && preg_match('#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $email) === 1;
    }
}
