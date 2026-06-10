<?php

/**
 * Address.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Message;

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
    public function __construct(
        private readonly string $email,
        private readonly string $name = '',
    ) {
    }

    /**
     * Parses `"Name" <email>`, `<email>` or `email` into an Address. A display
     * name passed explicitly takes precedence over one embedded in `$raw`.
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
