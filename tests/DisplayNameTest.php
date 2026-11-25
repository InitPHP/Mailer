<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Message\HeaderRenderer;
use InitPHP\Mailer\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

use function preg_match;

/**
 * A display name with a CR, LF or NUL is not rejected: it is made safe when the
 * header is written, exactly as in 3.0.0. The expected header values below were
 * recorded by running the 3.0.0 tag's source, so they pin the output byte for
 * byte; the rendered header block must also never contain a header that the
 * name smuggled in.
 */
final class DisplayNameTest extends TestCase
{
    /**
     * @return array<string, array{string, string, string}> name, From value, Reply-To value (3.0.0)
     */
    public static function names(): array
    {
        return [
            'name 0' => ['Ali', '"Ali" <s@x.com>', '"Ali" <r@x.com>'],
            'name 1' => ["Ali\r\nBcc: e@z.com", '"Ali\\r\\nBcc: e@z.com" <s@x.com>', '"Ali\\r\\nBcc: e@z.com" <r@x.com>'],
            'name 2' => ["Ali\nBcc: e@z.com", '"Ali\\nBcc: e@z.com" <s@x.com>', '"Ali\\nBcc: e@z.com" <r@x.com>'],
            'name 3' => ["Ali\rBcc: e@z.com", '"Ali\\rBcc: e@z.com" <s@x.com>', '"Ali\\rBcc: e@z.com" <r@x.com>'],
            'name 4' => ["Ali\0Veli", '"Ali\\000Veli" <s@x.com>', '"Ali\\000Veli" <r@x.com>'],
            'name 5' => ["Ali\r\n", '"Ali" <s@x.com>', '"Ali" <r@x.com>'],
            'name 6' => ["\r\nAli", '"Ali" <s@x.com>', '"Ali" <r@x.com>'],
            'name 7' => ["Şali\r\nBcc: e@z.com", '=?UTF-8?Q?=C5=9EaliBcc:=20e@z.com?= <s@x.com>', '=?UTF-8?Q?=C5=9EaliBcc:=20e@z.com?= <r@x.com>'],
            'name 8' => ["Şali\nX", '=?UTF-8?Q?=C5=9EaliX?= <s@x.com>', '=?UTF-8?Q?=C5=9EaliX?= <r@x.com>'],
            'name 9' => ["Şali\rX", '=?UTF-8?Q?=C5=9EaliX?= <s@x.com>', '=?UTF-8?Q?=C5=9EaliX?= <r@x.com>'],
            'name 10' => ["Şali\0X", '=?UTF-8?Q?=C5=9Eali=00X?= <s@x.com>', '=?UTF-8?Q?=C5=9Eali=00X?= <r@x.com>'],
            'name 11' => ["Ali \"Q\" \\ 'x'", "\"Ali \\\"Q\\\" \\\\ \\'x\\'\" <s@x.com>", "\"Ali \\\"Q\\\" \\\\ \\'x\\'\" <r@x.com>"],
            'name 12' => ["Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com Şali\r\nBcc: e@z.com ", "=?UTF-8?Q?=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliB?=\n =?UTF-8?Q?cc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.c?=\n =?UTF-8?Q?om=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9E?=\n =?UTF-8?Q?aliBcc:=20e@z.com?= <s@x.com>", "=?UTF-8?Q?=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliB?=\n =?UTF-8?Q?cc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.c?=\n =?UTF-8?Q?om=20=C5=9EaliBcc:=20e@z.com=20=C5=9EaliBcc:=20e@z.com=20=C5=9E?=\n =?UTF-8?Q?aliBcc:=20e@z.com?= <r@x.com>"],
            'name 13' => ["A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB A\r\nB ", '"A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB" <s@x.com>', '"A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB A\\r\\nB" <r@x.com>'],
        ];
    }

    /**
     * @return array<string, array{string, string, string, bool}>
     */
    public static function namesWithValidation(): array
    {
        $out = [];
        foreach (self::names() as $label => $row) {
            $out[$label . ', validate on'] = [...$row, true];
            $out[$label . ', validate off'] = [...$row, false];
        }

        return $out;
    }

    /**
     * @dataProvider namesWithValidation
     */
    public function testTheSenderNameIsWrittenAsIn300(string $name, string $from, string $replyTo, bool $validate): void
    {
        $transport = new RecordingTransport();
        Mailer::newInstance(['validate' => $validate])->setTransport($transport)
            ->setFrom('s@x.com', $name)->setTo('t@x.com')->send();

        $this->assertNameIsSafe($transport, $from, $replyTo === '' ? '' : $this->swap($replyTo, 's@x.com'));
    }

    /**
     * @dataProvider namesWithValidation
     */
    public function testTheReplyToNameIsWrittenAsIn300(string $name, string $from, string $replyTo, bool $validate): void
    {
        $transport = new RecordingTransport();
        Mailer::newInstance(['validate' => $validate])->setTransport($transport)
            ->setFrom('s@x.com')->setReplyTo('r@x.com', $name)->setTo('t@x.com')->send();

        $this->assertSame($replyTo, $transport->last()->headers['Reply-To']);
        $this->assertRenderedHeadersAreSafe($transport);
    }

    /**
     * @dataProvider namesWithValidation
     */
    public function testTheConfiguredSenderNameIsWrittenAsIn300(string $name, string $from, string $replyTo, bool $validate): void
    {
        $transport = new RecordingTransport();
        Mailer::newInstance(['validate' => $validate, 'fromEmail' => 's@x.com', 'fromName' => $name])
            ->setTransport($transport)->setTo('t@x.com')->send();

        $this->assertNameIsSafe($transport, $from, $this->swap($replyTo, 's@x.com'));
    }

    private function assertNameIsSafe(RecordingTransport $transport, string $from, string $replyTo): void
    {
        $headers = $transport->last()->headers;
        $this->assertSame($from, $headers['From']);
        $this->assertSame($replyTo, $headers['Reply-To']);
        $this->assertRenderedHeadersAreSafe($transport);
    }

    /**
     * Rendering is where a line break in a value would be refused; it must also
     * not have produced a header of its own from the name.
     */
    private function assertRenderedHeadersAreSafe(RecordingTransport $transport): void
    {
        $block = HeaderRenderer::render($transport->last()->headers, "\n");

        $this->assertSame(0, preg_match('/^Bcc:/mi', $block), 'The name must not start a Bcc header.');
        $this->assertSame(1, preg_match_all('/^From:/m', $block));
        $this->assertSame(1, preg_match_all('/^Reply-To:/m', $block));
        $this->assertStringNotContainsString("\0", $block);
    }

    /**
     * The recorded Reply-To value uses r@x.com; with the sender as Reply-To the
     * address is the sender's.
     */
    private function swap(string $replyTo, string $address): string
    {
        return str_replace('<r@x.com>', '<' . $address . '>', $replyTo);
    }
}
