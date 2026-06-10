<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Encoding;

use InitPHP\Mailer\Encoding\QuotedPrintableEncoder;
use PHPUnit\Framework\TestCase;

use function quoted_printable_decode;
use function str_repeat;

final class QuotedPrintableEncoderTest extends TestCase
{
    public function testCrlfModeDelegatesToNativeEncoder(): void
    {
        $encoded = (new QuotedPrintableEncoder("\r\n"))->encode('a=b');

        $this->assertSame('a=3Db', $encoded);
    }

    public function testEscapesEqualsSignInLfMode(): void
    {
        $encoded = (new QuotedPrintableEncoder("\n"))->encode('a=b');

        $this->assertSame('a=3Db', $encoded);
    }

    public function testPlainTextIsLeftUnchangedInLfMode(): void
    {
        $this->assertSame('Hello World', (new QuotedPrintableEncoder("\n"))->encode('Hello World'));
    }

    public function testTrimsOnlyTheTrailingLineEnding(): void
    {
        $encoded = (new QuotedPrintableEncoder("\n"))->encode("a=b\nc=d");

        $this->assertSame("a=3Db\nc=3Dd", $encoded);
    }

    /**
     * Regression: in 1.x the trailing line ending was trimmed with a positive
     * length instead of a negative one, so the encoder returned only the first
     * byte. A long body must survive intact.
     */
    public function testDoesNotTruncateLongBodiesInLfMode(): void
    {
        $body = str_repeat('The quick brown fox jumps over the lazy dog. ', 20);
        $encoded = (new QuotedPrintableEncoder("\n"))->encode($body);

        $this->assertGreaterThan(1, \strlen($encoded));
        $this->assertStringContainsString('quick brown fox', quoted_printable_decode($encoded));
    }

    public function testStripsUnwrapMarkers(): void
    {
        $encoded = (new QuotedPrintableEncoder("\r\n"))->encode('{unwrap}keep{/unwrap}');

        $this->assertSame('keep', $encoded);
    }
}
