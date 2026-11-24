<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Message;

use InitPHP\Mailer\Exception\AttachmentException;
use InitPHP\Mailer\Message\Attachment;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function chunk_split;
use function file_put_contents;
use function mkdir;
use function rmdir;
use function sys_get_temp_dir;
use function uniqid;
use function unlink;

final class AttachmentTest extends TestCase
{
    /** @var list<string> */
    private array $tempDirs = [];

    protected function tearDown(): void
    {
        foreach ($this->tempDirs as $dir) {
            foreach ((array) glob($dir . '/*') as $file) {
                @unlink((string) $file);
            }
            @rmdir($dir);
        }
        $this->tempDirs = [];
    }

    public function testFromContentUsesGivenMetadata(): void
    {
        $attachment = Attachment::fromContent('PAYLOAD', 'report.csv', 'attachment', 'text/csv');

        $this->assertSame('report.csv', $attachment->getName());
        $this->assertSame('attachment', $attachment->getDisposition());
        $this->assertSame('text/csv', $attachment->getMimeType());
        $this->assertSame(Attachment::MULTIPART_MIXED, $attachment->getMultipart());
        $this->assertSame(chunk_split(base64_encode('PAYLOAD')), $attachment->getEncodedContent());
    }

    public function testFromContentDefaultsTheMimeType(): void
    {
        $this->assertSame('application/octet-stream', Attachment::fromContent('x', 'a.bin')->getMimeType());
    }

    public function testFromPathReadsTheFile(): void
    {
        $path = $this->createFile('hello world', 'note.txt');
        $attachment = Attachment::fromPath($path);

        $this->assertSame('note.txt', $attachment->getName());
        $this->assertStringStartsWith('text/', $attachment->getMimeType());
        $this->assertSame(chunk_split(base64_encode('hello world')), $attachment->getEncodedContent());
        $this->assertTrue($attachment->matchesSource($path));
    }

    public function testFromPathHonoursRenameAndExplicitMime(): void
    {
        $path = $this->createFile('x', 'original.dat');
        $attachment = Attachment::fromPath($path, 'inline', 'renamed.dat', 'application/x-test');

        $this->assertSame('renamed.dat', $attachment->getName());
        $this->assertSame('inline', $attachment->getDisposition());
        $this->assertSame('application/x-test', $attachment->getMimeType());
    }

    public function testFromPathThrowsWhenTheFileIsMissing(): void
    {
        $this->expectException(AttachmentException::class);

        Attachment::fromPath(sys_get_temp_dir() . '/does-not-exist-' . uniqid('', true));
    }

    public function testMakeInlinePromotesToRelatedWithACid(): void
    {
        $attachment = Attachment::fromContent('img', 'logo.png', 'inline', 'image/png');
        $cid = $attachment->makeInline();

        $this->assertSame(Attachment::MULTIPART_RELATED, $attachment->getMultipart());
        $this->assertSame($cid, $attachment->getCid());
        $this->assertStringContainsString('logo.png@', (string) $attachment->getCid());
    }

    private function createFile(string $contents, string $name): string
    {
        $dir = sys_get_temp_dir() . '/mailer-test-' . uniqid('', true);
        mkdir($dir);
        $this->tempDirs[] = $dir;
        $path = $dir . '/' . $name;
        file_put_contents($path, $contents);

        return $path;
    }

    /**
     * @dataProvider unsafeMetadata
     */
    public function testFromContentRejectsALineBreakOrNulInTheMetadata(string $name, string $disposition, string $mime): void
    {
        $this->expectException(AttachmentException::class);

        Attachment::fromContent('PAYLOAD', $name, $disposition, $mime);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function unsafeMetadata(): array
    {
        return [
            'CRLF in the name'        => ["a.txt\r\nBcc: x@example.test", 'attachment', 'text/plain'],
            'NUL in the name'         => ["a\0.txt", 'attachment', 'text/plain'],
            'LF in the disposition'   => ['a.txt', "attachment\nBcc: x@example.test", 'text/plain'],
            'CRLF in the MIME type'   => ['a.txt', 'attachment', "text/plain\r\nBcc: x@example.test"],
        ];
    }

    public function testFromPathRejectsALineBreakInTheNewName(): void
    {
        $dir = sys_get_temp_dir() . '/initphp-mailer-' . uniqid('', true);
        mkdir($dir);
        $this->tempDirs[] = $dir;
        file_put_contents($dir . '/a.txt', 'x');

        $this->expectException(AttachmentException::class);

        Attachment::fromPath($dir . '/a.txt', '', "b.txt\r\nBcc: x@example.test", 'text/plain');
    }

    public function testTheContentIdKeepsOnlySafeCharactersOfTheName(): void
    {
        $attachment = Attachment::fromContent('x', 'my logo">.png', 'inline');

        $cid = $attachment->makeInline();

        $this->assertSame(1, preg_match('/\A[A-Za-z0-9._-]+@[0-9a-f.]+\z/', $cid));
        $this->assertStringStartsWith('my_logo__.png@', $cid);
    }
}
