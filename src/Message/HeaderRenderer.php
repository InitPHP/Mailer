<?php

/**
 * HeaderRenderer.php
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

use function trim;

/**
 * Renders an ordered map of header name => value into a header block. Empty
 * values are skipped, and each line is terminated with the given line ending.
 */
final class HeaderRenderer
{
    /**
     * @param array<string, string> $headers
     */
    public static function render(array $headers, string $newline): string
    {
        $out = '';
        foreach ($headers as $key => $value) {
            $value = trim($value);
            if ($value !== '') {
                $out .= $key . ': ' . $value . $newline;
            }
        }

        return $out;
    }
}
