<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Encoding;

use InitPHP\Mailer\Encoding\WordWrapper;
use PHPUnit\Framework\TestCase;

use function explode;
use function mb_strlen;
use function str_repeat;

final class WordWrapperTest extends TestCase
{
    public function testWrapsLongLinesAtTheConfiguredWidth(): void
    {
        $wrapped = (new WordWrapper(20, "\n"))->wrap(str_repeat('word ', 30));

        foreach (explode("\n", $wrapped) as $line) {
            $this->assertLessThanOrEqual(20, mb_strlen($line, '8bit'));
        }
    }

    public function testShortTextIsReturnedWithATrailingNewline(): void
    {
        $this->assertSame("Hello\n", (new WordWrapper(76, "\n"))->wrap('Hello'));
    }

    public function testContentBetweenUnwrapMarkersIsPreserved(): void
    {
        $long = str_repeat('x', 100);
        $wrapped = (new WordWrapper(20, "\n"))->wrap('{unwrap}' . $long . '{/unwrap}');

        $this->assertStringContainsString($long, $wrapped);
    }

    public function testExplicitWidthOverridesTheConfiguredWidth(): void
    {
        $wrapped = (new WordWrapper(76, "\n"))->wrap(str_repeat('word ', 20), 10);

        foreach (explode("\n", $wrapped) as $line) {
            $this->assertLessThanOrEqual(10, mb_strlen($line, '8bit'));
        }
    }
}
