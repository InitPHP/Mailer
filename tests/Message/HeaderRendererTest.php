<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Message;

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
}
