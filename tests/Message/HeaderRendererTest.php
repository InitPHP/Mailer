<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Message;

use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Message\HeaderRenderer;
use PHPUnit\Framework\TestCase;

final class HeaderRendererTest extends TestCase
{
    public function testRendersHeadersWithTheGivenLineEnding(): void
    {
        $out = HeaderRenderer::render(['From' => 'a@b.com', 'Subject' => 'Hi'], "\r\n");

        $this->assertSame("From: a@b.com\r\nSubject: Hi\r\n", $out);
    }

    public function testSkipsEmptyValues(): void
    {
        $out = HeaderRenderer::render(['A' => '1', 'B' => '', 'C' => '   ', 'D' => '4'], "\n");

        $this->assertSame("A: 1\nD: 4\n", $out);
    }

    public function testReturnsEmptyStringForNoHeaders(): void
    {
        $this->assertSame('', HeaderRenderer::render([], "\r\n"));
    }

    public function testAcceptsAFoldedValue(): void
    {
        $out = HeaderRenderer::render(['Subject' => "=?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?="], "\r\n");

        $this->assertSame("Subject: =?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?=\r\n", $out);
    }

    /**
     * @dataProvider unsafeValues
     */
    public function testRejectsAValueThatWouldStartAnotherHeader(string $value): void
    {
        $this->expectException(MailerException::class);

        HeaderRenderer::render(['X-A' => $value], "\r\n");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeValues(): array
    {
        return [
            'CRLF'                => ["v\r\nBcc: e@z.com"],
            'LF'                  => ["v\nBcc: e@z.com"],
            'CR'                  => ["v\rBcc: e@z.com"],
            'NUL'                 => ["v\0w"],
            'blank line, then fold' => ["v\r\n\r\n x"],
        ];
    }

    /**
     * @dataProvider invalidNames
     */
    public function testRejectsAnInvalidName(string $name): void
    {
        $this->expectException(MailerException::class);

        HeaderRenderer::render([$name => 'v'], "\r\n");
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'CRLF'  => ["X\r\nBcc"],
            'trailing LF' => ["X-A\n"],
            'tab'   => ["X-A\t"],
            'space' => ['X A'],
            'colon' => ['X:A'],
        ];
    }

    public function testDoesNotCheckAnEmptyValue(): void
    {
        $this->assertSame('', HeaderRenderer::render(['X-A' => "\n"], "\r\n"));
    }
}
