<?php

/**
 * PreparedEmail.php
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

/**
 * An immutable, transport-ready message. The {@see \InitPHP\Mailer\Mailer}
 * assembles one of these and hands it to a transport, which decides how to put
 * `To`/`Subject`/`Bcc` on the wire (those vary by transport, so they are kept
 * out of {@see $headers}).
 */
final class PreparedEmail
{
    /**
     * @param list<string>          $to             Bare recipient addresses.
     * @param list<string>          $cc             Bare carbon-copy addresses.
     * @param list<string>          $bcc            Bare blind-carbon-copy addresses.
     * @param string                $subject        Final (already encoded) Subject value.
     * @param array<string, string> $headers        General headers, excluding To/Subject/Bcc.
     * @param string                $contentHeaders Content-Type/Content-Transfer-Encoding block (no trailing newline).
     * @param string                $body           Encoded MIME body.
     */
    public function __construct(
        public readonly Address $from,
        public readonly array $to,
        public readonly array $cc,
        public readonly array $bcc,
        public readonly string $subject,
        public readonly array $headers,
        public readonly string $contentHeaders,
        public readonly string $body,
        public readonly string $newline,
    ) {
    }
}
