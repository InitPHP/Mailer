<?php

/**
 * HeaderRenderer.php
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

use InitPHP\Mailer\Exception\MailerException;

use function preg_match;
use function preg_replace;
use function trim;

/**
 * Renders an ordered map of header name => value into a header block. Empty
 * values are skipped, and each line is terminated with the given line ending.
 *
 * Every name and value is checked on the way out, so a transport never writes a
 * header that was built by hand (a `PreparedEmail` is public) with a line break
 * that would start another header. The only line break a value may contain is a
 * fold: a line ending directly followed by a space or tab, as the Q-encoder
 * produces for a long subject.
 */
final class HeaderRenderer
{
    /**
     * @param array<string, string> $headers
     *
     * @throws MailerException When a header name is not a valid field name, or a
     *                         value contains a line break other than a fold, or a NUL.
     */
    public static function render(array $headers, string $newline): string
    {
        $out = '';
        foreach ($headers as $key => $value) {
            $value = trim($value);
            if ($value !== '') {
                self::assertSafe((string) $key, $value);
                $out .= $key . ': ' . $value . $newline;
            }
        }

        return $out;
    }

    /**
     * Whether `$name` is usable as a header field name: printable ASCII other
     * than `:`, so no space, line break, NUL or colon.
     */
    public static function isValidName(string $name): bool
    {
        return preg_match('/\A[\x21-\x39\x3B-\x7E]+\z/', $name) === 1;
    }

    /**
     * @throws MailerException
     */
    public static function assertSafe(string $name, string $value): void
    {
        if (!self::isValidName($name)) {
            throw new MailerException('A header name must be made of printable ASCII characters other than ":".');
        }

        $unfolded = (string) preg_replace('/(?:\r\n|\n\r|\n|\r)(?=[ \t])/', '', $value);
        if (preg_match('/[\r\n\0]/', $unfolded) === 1) {
            throw new MailerException(\sprintf('The "%s" header value must not contain a line break or a NUL character.', $name));
        }
    }
}
