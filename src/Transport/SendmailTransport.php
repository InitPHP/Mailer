<?php

/**
 * SendmailTransport.php
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

use function fwrite;
use function implode;
use function pclose;
use function popen;

/**
 * Sends by piping the message to a local sendmail binary.
 *
 * The pipe is injectable — a callable that receives the command and the raw
 * message and returns the process exit status — so the transport can be
 * unit-tested without spawning a real process.
 */
final class SendmailTransport implements TransportInterface
{
    /** @var callable(string, string): int */
    private $pipe;

    /**
     * @param (callable(string, string): int)|null $pipe
     */
    public function __construct(
        private readonly string $mailPath,
        private readonly EmailValidator $validator,
        ?callable $pipe = null,
    ) {
        $this->pipe = $pipe ?? self::openPipe(...);
    }

    public function send(PreparedEmail $email): void
    {
        $from = $email->from->getEmail();
        $fromArg = $this->validator->isShellSafe($from) ? '-f ' . $from : '';

        $headers = $email->headers;
        $headers['To'] = implode(', ', $email->to);
        $headers['Subject'] = $email->subject;
        if ($email->bcc !== []) {
            $headers['Bcc'] = implode(', ', $email->bcc);
        }

        $message = HeaderRenderer::render($headers, $email->newline)
            . $email->contentHeaders . $email->newline . $email->newline . $email->body;

        $command = $this->mailPath . ' -oi ' . $fromArg . ' -t';
        $status = ($this->pipe)($command, $message);

        if ($status !== 0) {
            throw new TransportException(
                \sprintf('sendmail returned a non-zero exit status: %d.', $status),
                $status,
            );
        }
    }

    /**
     * @throws TransportException
     */
    private static function openPipe(string $command, string $message): int
    {
        $handle = @popen($command, 'w');
        if (!\is_resource($handle)) {
            throw new TransportException('Could not open a pipe to the sendmail binary.');
        }
        fwrite($handle, $message);

        return pclose($handle);
    }
}
