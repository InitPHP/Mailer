<?php

/**
 * WordWrapper.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Encoding;

use function explode;
use function mb_strlen;
use function mb_substr;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function str_replace;
use function wordwrap;

/**
 * Wraps plain-text message bodies at a fixed column. Content between
 * `{unwrap}…{/unwrap}` markers (for example long URLs) is preserved verbatim,
 * and lines that look like links are never broken.
 */
final class WordWrapper
{
    public function __construct(
        private readonly int $wrapChars,
        private readonly string $newline,
    ) {
    }

    /**
     * @param int|null $chars Column to wrap at, or null to use the configured width.
     */
    public function wrap(string $str, ?int $chars = null): string
    {
        if ($chars === null || $chars <= 0) {
            $chars = $this->wrapChars > 0 ? $this->wrapChars : 76;
        }

        if (str_contains($str, "\r")) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        $str = (string) preg_replace('| +\n|', "\n", $str);

        $unwrap = [];
        preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches);
        $count = \count($matches[0]);
        for ($i = 0; $i < $count; $i++) {
            $unwrap[] = $matches[1][$i];
            $str = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
        }

        $str = wordwrap($str, $chars, "\n", false);

        $output = '';
        foreach (explode("\n", $str) as $line) {
            if (mb_strlen($line, '8bit') <= $chars) {
                $output .= $line . $this->newline;
                continue;
            }
            $temp = '';
            do {
                if (preg_match('!\[url.+\]|://|www\.!', $line) === 1) {
                    break;
                }
                $temp .= mb_substr($line, 0, $chars - 1, '8bit');
                $line = mb_substr($line, $chars - 1, null, '8bit');
            } while (mb_strlen($line, '8bit') > $chars);
            if ($temp !== '') {
                $output .= $temp . $this->newline;
            }
            $output .= $line . $this->newline;
        }

        foreach ($unwrap as $key => $value) {
            $output = str_replace('{{unwrapped' . $key . '}}', $value, $output);
        }

        return $output;
    }
}
