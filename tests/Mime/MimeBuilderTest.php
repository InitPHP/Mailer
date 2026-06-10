<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Mime;

use InitPHP\Mailer\Encoding\QuotedPrintableEncoder;
use InitPHP\Mailer\Encoding\WordWrapper;
use InitPHP\Mailer\Message\Attachment;
use InitPHP\Mailer\Mime\MimeBuilder;
use PHPUnit\Framework\TestCase;

use function base64_encode;

final class MimeBuilderTest extends TestCase
{
    private function builder(bool $sendMultipart = true): MimeBuilder
    {
        $counter = 0;
        $factory = static function (string $prefix) use (&$counter): string {
            return $prefix . (++$counter);
        };

        return new MimeBuilder(
            'UTF-8',
            "\n",
            new QuotedPrintableEncoder("\n"),
            new WordWrapper(76, "\n"),
            true,
            $sendMultipart,
            $factory,
        );
    }

    public function testBuildsAPlainTextMessage(): void
    {
        $result = $this->builder()->build('text', 'Hello', '', [], '8bit');

        $this->assertSame(
            "Content-Type: text/plain; charset=UTF-8\nContent-Transfer-Encoding: 8bit",
            $result['headers'],
        );
        $this->assertSame("Hello\n", $result['body']);
    }

    public function testBuildsAMultipartAlternativeHtmlMessage(): void
    {
        $result = $this->builder()->build('html', '<p>Hi</p>', '', [], '8bit');

        $this->assertSame('Content-Type: multipart/alternative; boundary="B_ALT_1"', $result['headers']);
        $this->assertStringContainsString('--B_ALT_1', $result['body']);
        $this->assertStringContainsString('Content-Type: text/plain; charset=UTF-8', $result['body']);
        $this->assertStringContainsString('Content-Type: text/html; charset=UTF-8', $result['body']);
        $this->assertStringContainsString('Content-Transfer-Encoding: quoted-printable', $result['body']);
        $this->assertStringContainsString('=3Cp=3EHi=3C/p=3E', $result['body']);
        $this->assertStringEndsWith('--B_ALT_1--', rtrim($result['body']));
    }

    public function testNonMultipartHtmlIsNotTruncated(): void
    {
        $result = $this->builder(false)->build('html', '<p>Hello world</p>', '', [], '8bit');

        $this->assertSame(
            "Content-Type: text/html; charset=UTF-8\nContent-Transfer-Encoding: quoted-printable",
            $result['headers'],
        );
        $this->assertGreaterThan(10, \strlen($result['body']));
        $this->assertStringContainsString('Hello world', quoted_printable_decode($result['body']));
    }

    public function testBuildsPlainTextWithAttachment(): void
    {
        $attachment = Attachment::fromContent('DATA', 'file.txt', 'attachment', 'text/plain');
        $result = $this->builder()->build('text', 'Body', '', [$attachment], '8bit');

        $this->assertSame('Content-Type: multipart/mixed; boundary="B_ATC_1"', $result['headers']);
        $this->assertStringContainsString('Content-Type: text/plain; name="file.txt"', $result['body']);
        $this->assertStringContainsString('Content-Transfer-Encoding: base64', $result['body']);
        $this->assertStringContainsString(base64_encode('DATA'), $result['body']);
        $this->assertStringEndsWith('--B_ATC_1--', $result['body']);
    }

    /**
     * Regression: with only inline (related) attachments, 1.x wrote the bare
     * boundary id into the headers instead of the multipart/related header.
     */
    public function testInlineOnlyAttachmentProducesARelatedHeader(): void
    {
        $inline = Attachment::fromContent('IMG', 'logo.png', 'inline', 'image/png');
        $inline->makeInline();

        $result = $this->builder()->build('html', '<p>Hi</p>', '', [$inline], '8bit');

        $this->assertStringStartsWith('Content-Type: multipart/related; boundary="B_REL_', $result['headers']);
        $this->assertStringContainsString('Content-ID: <', $result['body']);
    }

    public function testMixedAttachmentProducesAMixedHeader(): void
    {
        $attachment = Attachment::fromContent('DATA', 'file.bin');
        $result = $this->builder()->build('html', '<p>Hi</p>', '', [$attachment], '8bit');

        $this->assertStringStartsWith('Content-Type: multipart/mixed; boundary="B_ATC_', $result['headers']);
    }

    public function testUsesTheConfiguredAlternativeMessage(): void
    {
        $result = $this->builder()->build('html', '<p>Hi</p>', 'Plain alternative', [], '8bit');

        $this->assertStringContainsString('Plain alternative', $result['body']);
    }
}
