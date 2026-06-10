<?php

/**
 * MimeBuilder.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer\Mime;

use InitPHP\Mailer\Encoding\QuotedPrintableEncoder;
use InitPHP\Mailer\Encoding\WordWrapper;
use InitPHP\Mailer\Message\Attachment;

use function mb_strlen;
use function preg_match;
use function preg_replace;
use function str_repeat;
use function str_replace;
use function strip_tags;
use function trim;
use function uniqid;

/**
 * Builds the MIME content headers and body for a message. The result is
 * transport-agnostic: {@see build()} returns the `Content-Type` /
 * `Content-Transfer-Encoding` block separately from the body so each transport
 * can place them where it needs to.
 *
 * The boundary factory is injectable so the generated MIME can be asserted
 * byte-for-byte in tests.
 */
final class MimeBuilder
{
    /** @var callable(string): string */
    private $boundaryFactory;

    /**
     * @param (callable(string): string)|null $boundaryFactory Returns a unique
     *        boundary string for the given prefix; defaults to `uniqid()`.
     */
    public function __construct(
        private readonly string $charset,
        private readonly string $newline,
        private readonly QuotedPrintableEncoder $quotedPrintable,
        private readonly WordWrapper $wordWrapper,
        private readonly bool $wordWrap,
        private readonly bool $sendMultipart,
        ?callable $boundaryFactory = null,
    ) {
        $this->boundaryFactory = $boundaryFactory ?? static fn (string $prefix): string => uniqid($prefix, true);
    }

    /**
     * @param Attachment[] $attachments
     * @param string       $encoding    `7bit` or `8bit`.
     *
     * @return array{headers: string, body: string}
     */
    public function build(string $mailType, string $body, string $altMessage, array $attachments, string $encoding): array
    {
        if ($this->wordWrap && $mailType !== 'html') {
            $body = $this->wordWrapper->wrap($body);
        }

        return match ($this->contentType($mailType, $attachments)) {
            'html'         => $this->buildHtml($body, $altMessage, $encoding),
            'plain-attach' => $this->buildPlainAttach($body, $attachments, $encoding),
            'html-attach'  => $this->buildHtmlAttach($body, $altMessage, $attachments, $encoding),
            default        => $this->buildPlain($body, $encoding),
        };
    }

    /**
     * @param Attachment[] $attachments
     */
    private function contentType(string $mailType, array $attachments): string
    {
        if ($mailType === 'html') {
            return $attachments === [] ? 'html' : 'html-attach';
        }

        return $attachments === [] ? 'plain' : 'plain-attach';
    }

    /**
     * @return array{headers: string, body: string}
     */
    private function buildPlain(string $body, string $encoding): array
    {
        return [
            'headers' => 'Content-Type: text/plain; charset=' . $this->charset . $this->newline
                . 'Content-Transfer-Encoding: ' . $encoding,
            'body'    => $body,
        ];
    }

    /**
     * @return array{headers: string, body: string}
     */
    private function buildHtml(string $html, string $altMessage, string $encoding): array
    {
        $nl = $this->newline;

        if (!$this->sendMultipart) {
            return [
                'headers' => 'Content-Type: text/html; charset=' . $this->charset . $nl
                    . 'Content-Transfer-Encoding: quoted-printable',
                'body'    => $this->quotedPrintable->encode($html) . $nl . $nl,
            ];
        }

        $boundary = ($this->boundaryFactory)('B_ALT_');
        $body = $this->mimePreamble() . $nl . $nl
            . '--' . $boundary . $nl
            . 'Content-Type: text/plain; charset=' . $this->charset . $nl
            . 'Content-Transfer-Encoding: ' . $encoding . $nl . $nl
            . $this->altMessage($altMessage, $html) . $nl . $nl
            . '--' . $boundary . $nl
            . 'Content-Type: text/html; charset=' . $this->charset . $nl
            . 'Content-Transfer-Encoding: quoted-printable' . $nl . $nl
            . $this->quotedPrintable->encode($html) . $nl . $nl
            . '--' . $boundary . '--';

        return [
            'headers' => 'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
            'body'    => $body,
        ];
    }

    /**
     * @param Attachment[] $attachments
     *
     * @return array{headers: string, body: string}
     */
    private function buildPlainAttach(string $body, array $attachments, string $encoding): array
    {
        $nl = $this->newline;
        $boundary = ($this->boundaryFactory)('B_ATC_');

        $out = $this->mimePreamble() . $nl . $nl
            . '--' . $boundary . $nl
            . 'Content-Type: text/plain; charset=' . $this->charset . $nl
            . 'Content-Transfer-Encoding: ' . $encoding . $nl . $nl
            . $body . $nl . $nl;
        $this->appendAttachments($out, $boundary, null, $attachments);

        return [
            'headers' => 'Content-Type: multipart/mixed; boundary="' . $boundary . '"',
            'body'    => $out,
        ];
    }

    /**
     * @param Attachment[] $attachments
     *
     * @return array{headers: string, body: string}
     */
    private function buildHtmlAttach(string $html, string $altMessage, array $attachments, string $encoding): array
    {
        $nl = $this->newline;
        $altBoundary = ($this->boundaryFactory)('B_ALT_');
        $body = '';
        $contentHeaders = '';
        $lastBoundary = null;
        $relBoundary = null;
        $atcBoundary = null;

        if ($this->hasMultipart($attachments, Attachment::MULTIPART_MIXED)) {
            $atcBoundary = ($this->boundaryFactory)('B_ATC_');
            $contentHeaders = 'Content-Type: multipart/mixed; boundary="' . $atcBoundary . '"';
            $lastBoundary = $atcBoundary;
        }
        if ($this->hasMultipart($attachments, Attachment::MULTIPART_RELATED)) {
            $relBoundary = ($this->boundaryFactory)('B_REL_');
            $relHeader = 'Content-Type: multipart/related; boundary="' . $relBoundary . '"';
            if ($lastBoundary !== null) {
                $body .= '--' . $lastBoundary . $nl . $relHeader;
            } else {
                // 1.x wrote the bare boundary id here instead of the header.
                $contentHeaders = $relHeader;
            }
            $lastBoundary = $relBoundary;
        }
        if ($lastBoundary === null) {
            $atcBoundary = ($this->boundaryFactory)('B_ATC_');
            $contentHeaders = 'Content-Type: multipart/mixed; boundary="' . $atcBoundary . '"';
            $lastBoundary = $atcBoundary;
        }

        if (mb_strlen($body, '8bit') > 0) {
            $body .= $nl . $nl;
        }
        $body .= $this->mimePreamble() . $nl . $nl
            . '--' . $lastBoundary . $nl
            . 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"' . $nl . $nl
            . '--' . $altBoundary . $nl
            . 'Content-Type: text/plain; charset=' . $this->charset . $nl
            . 'Content-Transfer-Encoding: ' . $encoding . $nl . $nl
            . $this->altMessage($altMessage, $html) . $nl . $nl
            . '--' . $altBoundary . $nl
            . 'Content-Type: text/html; charset=' . $this->charset . $nl
            . 'Content-Transfer-Encoding: quoted-printable' . $nl . $nl
            . $this->quotedPrintable->encode($html) . $nl . $nl
            . '--' . $altBoundary . '--' . $nl . $nl;

        if ($relBoundary !== null) {
            $body .= $nl . $nl;
            $this->appendAttachments($body, $relBoundary, Attachment::MULTIPART_RELATED, $attachments);
        }
        if ($atcBoundary !== null) {
            $body .= $nl . $nl;
            $this->appendAttachments($body, $atcBoundary, Attachment::MULTIPART_MIXED, $attachments);
        }

        return ['headers' => $contentHeaders, 'body' => $body];
    }

    /**
     * @param Attachment[] $attachments
     */
    private function hasMultipart(array $attachments, string $type): bool
    {
        foreach ($attachments as $attachment) {
            if ($attachment->getMultipart() === $type) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param Attachment[] $attachments
     */
    private function appendAttachments(string &$body, string $boundary, ?string $multipart, array $attachments): void
    {
        $written = false;
        foreach ($attachments as $attachment) {
            if ($multipart !== null && $attachment->getMultipart() !== $multipart) {
                continue;
            }
            $cid = $attachment->getCid();
            $body .= '--' . $boundary . $this->newline
                . 'Content-Type: ' . $attachment->getMimeType() . '; name="' . $attachment->getName() . '"' . $this->newline
                . 'Content-Disposition: ' . $attachment->getDisposition() . ';' . $this->newline
                . 'Content-Transfer-Encoding: base64' . $this->newline
                . ($cid !== null ? 'Content-ID: <' . $cid . '>' . $this->newline : '')
                . $this->newline
                . $attachment->getEncodedContent() . $this->newline;
            $written = true;
        }
        if ($written) {
            $body .= '--' . $boundary . '--';
        }
    }

    private function mimePreamble(): string
    {
        return 'This is a multi-part message in MIME format.' . $this->newline
            . 'Your email application may not support this format.';
    }

    /**
     * Returns the alternative plain-text body for an HTML message: the
     * configured alternative if set, otherwise one derived from the HTML.
     */
    private function altMessage(string $altMessage, string $html): string
    {
        if ($altMessage !== '') {
            return $this->wordWrap ? $this->wordWrapper->wrap($altMessage, 76) : $altMessage;
        }

        $body = preg_match('/\<body.*?\>(.*)\<\/body\>/si', $html, $match) === 1 ? $match[1] : $html;
        $body = (string) preg_replace('#<!--(.*)--\>#', '', $body);
        $body = str_replace("\t", '', trim(strip_tags($body)));
        for ($i = 20; $i >= 3; $i--) {
            $body = str_replace(str_repeat("\n", $i), "\n\n", $body);
        }
        $body = (string) preg_replace('| +|', ' ', $body);

        return $this->wordWrap ? $this->wordWrapper->wrap($body, 76) : $body;
    }
}
