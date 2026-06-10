<?php

/**
 * MailTransport.php
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
use InitPHP\Mailer\Message\HeaderRenderer;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Validation\EmailValidator;

use function implode;
use function mail;
use function rtrim;

/**
 * Sends through PHP's native `mail()` function. `To` and `Subject` are passed
 * as the function's dedicated arguments, so they are kept out of the header
 * block; everything else (including the MIME content headers) is passed as the
 * additional headers.
 *
 * The underlying function is injectable so the transport can be unit-tested
 * without actually sending mail.
 */
final class MailTransport implements TransportInterface
{
    /** @var callable(string, string, string, string, string): bool */
    private $mailFunction;

    /**
     * @param (callable(string, string, string, string, string): bool)|null $mailFunction
     */
    public function __construct(
        private readonly EmailValidator $validator,
        ?callable $mailFunction = null,
    ) {
        $this->mailFunction = $mailFunction ?? static fn (
            string $to,
            string $subject,
            string $message,
            string $headers,
            string $params,
        ): bool => mail($to, $subject, $message, $headers, $params);
    }

    public function send(PreparedEmail $email): void
    {
        $headers = $email->headers;
        if ($email->bcc !== []) {
            $headers['Bcc'] = implode(', ', $email->bcc);
        }
        $headerString = rtrim(HeaderRenderer::render($headers, $email->newline) . $email->contentHeaders);

        $from = $email->from->getEmail();
        $params = $this->validator->isShellSafe($from) ? '-f ' . $from : '';

        $sent = ($this->mailFunction)(
            implode(', ', $email->to),
            $email->subject,
            $email->body,
            $headerString,
            $params,
        );

        if ($sent !== true) {
            throw new TransportException('The mail() function failed to send the message.');
        }
    }
}
