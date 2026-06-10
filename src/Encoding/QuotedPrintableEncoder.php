<?php

/**
 * QuotedPrintableEncoder.php
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

use function dechex;
use function explode;
use function mb_strlen;
use function mb_substr;
use function preg_replace;
use function quoted_printable_encode;
use function str_replace;
use function strtoupper;

/**
 * Encodes message bodies as quoted-printable (RFC 2045). When the configured
 * line ending is CRLF the native `quoted_printable_encode()` is used; otherwise
 * a custom encoder runs so the chosen line ending is honoured, since some
 * servers only accept a bare LF.
 */
final class QuotedPrintableEncoder
{
    /**
     * Printable ASCII byte values that may be emitted verbatim.
     *
     * @var list<int>
     */
    private const ASCII_SAFE = [
        39, 40, 41, 42, 43, 44, 45, 46, 47, 58, 61, 63,
        48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
        65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79,
        80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
        97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109,
        110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122,
    ];

    public function __construct(
        private readonly string $crlf,
    ) {
    }

    public function encode(string $str): string
    {
        $str = str_replace(['{unwrap}', '{/unwrap}'], '', $str);

        if ($this->crlf === "\r\n") {
            return quoted_printable_encode($str);
        }

        $str = (string) preg_replace(['| +|', '/\x00+/'], [' ', ''], $str);
        if (str_contains($str, "\r")) {
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }

        $escape = '=';
        $out = '';
        foreach (explode("\n", $str) as $line) {
            $length = mb_strlen($line, '8bit');
            $temp = '';
            for ($i = 0; $i < $length; $i++) {
                $char = $line[$i];
                $ascii = \ord($char);
                if ($ascii === 32 || $ascii === 9) {
                    if ($i === ($length - 1)) {
                        $char = $escape . \sprintf('%02s', dechex($ascii));
                    }
                } elseif ($ascii === 61 || !\in_array($ascii, self::ASCII_SAFE, true)) {
                    $char = $escape . strtoupper(\sprintf('%02s', dechex($ascii)));
                }

                if ((mb_strlen($temp, '8bit') + mb_strlen($char, '8bit')) >= 76) {
                    $out .= $temp . $escape . $this->crlf;
                    $temp = '';
                }
                $temp .= $char;
            }
            $out .= $temp . $this->crlf;
        }

        return mb_substr($out, 0, -mb_strlen($this->crlf, '8bit'), '8bit');
    }
}
