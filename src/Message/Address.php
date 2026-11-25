<?php

/**
 * Address.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://muhammetsafak.com
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Message;

use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Validation\EmailValidator;

use function preg_match;
use function trim;

/**
 * An e-mail address with an optional display name. The bare addr-spec is kept
 * separate from the display name so the wire address (SMTP `MAIL FROM`,
 * `Return-Path`) is never contaminated by the name — the cause of the malformed
 * `MAIL FROM` in 1.x.
 */
final class Address
{
    /**
     * @throws InvalidAddressException When the address contains a CR, LF, NUL,
     *                                 `<` or `>`. This is checked even when
     *                                 address validation is off.
     */
    public function __construct(
        private readonly string $email,
        private readonly string $name = '',
    ) {
        // Only the address is refused. A display name with a line break or NUL
        // is not rejected: it is made safe when it is written (an ASCII name is
        // escaped, a non-ASCII name is Q-encoded with the line breaks removed),
        // as it always was.
        EmailValidator::assertSafeAddresses([$email]);
    }

    /**
     * Parses `"Name" <email>`, `<email>` or `email` into an Address. A display
     * name passed explicitly takes precedence over one embedded in `$raw`.
     *
     * @throws InvalidAddressException When the address part contains a CR, LF,
     *                                 NUL, `<` or `>`.
     */
    public static function parse(string $raw, string $name = ''): self
    {
        $raw = trim($raw);
        if (preg_match('/^(.*)<(.+)>$/', $raw, $matches) === 1) {
            if ($name === '') {
                $name = trim($matches[1], " \t\"");
            }
            $raw = $matches[2];
        }
        $email = trim($raw, "<> \t\n\r\0\x0B");

        return new self($email, trim($name));
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function hasName(): bool
    {
        return $this->name !== '';
    }
}
