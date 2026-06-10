<?php

/**
 * QEncoder.php
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

use function bin2hex;
use function iconv_mime_encode;
use function iconv_strlen;
use function iconv_substr;
use function implode;
use function mb_strlen;
use function mb_substr;
use function str_replace;
use function str_split;
use function strtoupper;

/**
 * Encodes header values (subjects, display names) that contain non-ASCII bytes
 * using the MIME "encoded-word" Q scheme of RFC 2047. UTF-8 input is handled by
 * `iconv_mime_encode()` when available, with a manual byte-wise fallback that
 * keeps each encoded line within the 76-character limit.
 */
final class QEncoder
{
    public function __construct(
        private readonly string $charset,
        private readonly string $crlf,
    ) {
    }

    public function encode(string $str): string
    {
        $str = str_replace(["\r", "\n"], '', $str);
        $chars = null;

        if ($this->charset === 'UTF-8') {
            $output = @iconv_mime_encode('', $str, [
                'scheme'           => 'Q',
                'line-length'      => 76,
                'input-charset'    => $this->charset,
                'output-charset'   => $this->charset,
                'line-break-chars' => $this->crlf,
            ]);
            if ($output !== false) {
                // Strip the leading ": " that iconv adds for the empty field name.
                return mb_substr($output, 2, null, '8bit');
            }
            $length = iconv_strlen($str, 'UTF-8');
            $chars = $length === false ? mb_strlen($str, 'UTF-8') : $length;
        }

        if ($chars === null) {
            $chars = mb_strlen($str, '8bit');
        }

        $output = '=?' . $this->charset . '?Q?';
        $lineLength = mb_strlen($output, '8bit');
        for ($i = 0; $i < $chars; $i++) {
            if ($this->charset === 'UTF-8') {
                $slice = iconv_substr($str, $i, 1, $this->charset);
                $chr = '=' . implode('=', str_split(strtoupper(bin2hex($slice === false ? '' : $slice)), 2));
            } else {
                $chr = strtoupper(bin2hex($str[$i]));
            }

            $chrLength = mb_strlen($chr, '8bit');
            if ($lineLength + $chrLength > 74) {
                $output .= '?=' . $this->crlf . ' =?' . $this->charset . '?Q?' . $chr;
                $lineLength = 6 + mb_strlen($this->charset, '8bit') + $chrLength;
            } else {
                $output .= $chr;
                $lineLength += $chrLength;
            }
        }

        return $output . '?=';
    }
}
