<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Encoding;

use InitPHP\Mailer\Encoding\QEncoder;
use PHPUnit\Framework\TestCase;

use function iconv_mime_decode;
use function str_starts_with;
use function strpos;

final class QEncoderTest extends TestCase
{
    private function utf8(): QEncoder
    {
        return new QEncoder('UTF-8', "\r\n");
    }

    public function testRoundTripsAsciiInput(): void
    {
        $encoded = $this->utf8()->encode('Hello World');

        $this->assertStringContainsString('=?UTF-8?Q?', $encoded);
        $this->assertSame('Hello World', iconv_mime_decode($encoded, 0, 'UTF-8'));
    }

    public function testRoundTripsNonAsciiInput(): void
    {
        $encoded = $this->utf8()->encode('Münir Şafak — café ☕');

        $this->assertSame('Münir Şafak — café ☕', iconv_mime_decode($encoded, 0, 'UTF-8'));
    }

    public function testEmitsAKnownEncodingForTurkishText(): void
    {
        $this->assertSame('=?UTF-8?Q?M=C3=BCnir=20=C5=9Eafak?=', $this->utf8()->encode('Münir Şafak'));
    }

    public function testStripsNewlinesFromInput(): void
    {
        $encoded = $this->utf8()->encode("Line1\r\nLine2");

        $this->assertSame('Line1Line2', iconv_mime_decode($encoded, 0, 'UTF-8'));
    }

    public function testNonUtf8CharsetUsesItsOwnLabel(): void
    {
        $encoded = (new QEncoder('ISO-8859-1', "\r\n"))->encode('Test');

        $this->assertTrue(str_starts_with($encoded, '=?ISO-8859-1?Q?'));
    }

    public function testLongInputIsFoldedWithinTheLineLimit(): void
    {
        $encoded = $this->utf8()->encode('Ä ' . str_repeat('long header value ', 20));

        // Folded encoded-words are separated by CRLF + a leading space.
        $this->assertNotFalse(strpos($encoded, "\r\n "));
        foreach (explode("\r\n", $encoded) as $line) {
            $this->assertLessThanOrEqual(78, \strlen($line));
        }
    }
}
