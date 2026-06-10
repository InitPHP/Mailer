<?php

/**
 * Attachment.php
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

use InitPHP\Mailer\Exception\AttachmentException;

use function base64_encode;
use function basename;
use function chunk_split;
use function file_get_contents;
use function is_file;
use function is_readable;
use function mime_content_type;
use function uniqid;

/**
 * A single attachment: either a file read from disk ({@see fromPath()}) or
 * in-memory/generated content ({@see fromContent()}). An attachment may be
 * promoted to an inline part with a Content-ID via {@see makeInline()}.
 */
final class Attachment
{
    public const MULTIPART_MIXED = 'mixed';
    public const MULTIPART_RELATED = 'related';

    private string $multipart = self::MULTIPART_MIXED;

    private ?string $cid = null;

    /**
     * @param string $source The identifier used to look the attachment up
     *                       again (the original path, or the given name for
     *                       in-memory content).
     */
    private function __construct(
        private readonly string $source,
        private readonly string $name,
        private readonly string $disposition,
        private readonly string $mimeType,
        private readonly string $content,
    ) {
    }

    /**
     * Builds an attachment from a file on disk.
     *
     * @throws AttachmentException When the file cannot be read or its type
     *                             cannot be detected.
     */
    public static function fromPath(
        string $path,
        string $disposition = '',
        ?string $newName = null,
        ?string $mime = null,
    ): self {
        if (!is_file($path) || !is_readable($path)) {
            throw new AttachmentException(\sprintf('The file "%s" could not be read.', $path));
        }
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new AttachmentException(\sprintf('The file "%s" could not be read.', $path));
        }

        return new self(
            $path,
            $newName ?? basename($path),
            $disposition === '' ? 'attachment' : $disposition,
            $mime ?? self::detectMime($path),
            $content,
        );
    }

    /**
     * Builds an attachment from raw, in-memory content (for example a rendered
     * PDF). For a stream, pass `stream_get_contents($handle)`.
     */
    public static function fromContent(
        string $content,
        string $name,
        string $disposition = 'attachment',
        ?string $mime = null,
    ): self {
        return new self(
            $name,
            $name,
            $disposition === '' ? 'attachment' : $disposition,
            $mime ?? 'application/octet-stream',
            $content,
        );
    }

    /**
     * Promotes the attachment to an inline `related` part and assigns it a
     * Content-ID, returning the generated CID.
     */
    public function makeInline(): string
    {
        $this->multipart = self::MULTIPART_RELATED;
        $this->cid = uniqid(basename($this->name) . '@', true);

        return $this->cid;
    }

    /**
     * Whether this attachment was created from the given source identifier.
     */
    public function matchesSource(string $source): bool
    {
        return $this->source === $source;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDisposition(): string
    {
        return $this->disposition;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getMultipart(): string
    {
        return $this->multipart;
    }

    public function getCid(): ?string
    {
        return $this->cid;
    }

    /**
     * The content base64-encoded and split into 76-character lines, ready to be
     * placed in a MIME part.
     */
    public function getEncodedContent(): string
    {
        return chunk_split(base64_encode($this->content));
    }

    /**
     * @throws AttachmentException
     */
    private static function detectMime(string $path): string
    {
        $mime = @mime_content_type($path);
        if ($mime === false) {
            throw new AttachmentException(
                'The actual type of the file could not be detected. You can specify it manually.',
            );
        }

        return $mime;
    }
}
