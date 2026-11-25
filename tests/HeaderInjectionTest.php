<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

use function array_keys;
use function array_map;
use function array_unique;
use function iconv_mime_decode;
use function preg_match;

/**
 * A CR, LF or NUL in a field that ends up in a header or an SMTP command must
 * never get through, and it must not depend on the `validate` option.
 */
final class HeaderInjectionTest extends TestCase
{
    private RecordingTransport $transport;

    /**
     * @param array<string, mixed> $config
     */
    private function mailer(array $config = []): Mailer
    {
        $this->transport = new RecordingTransport();

        return Mailer::newInstance($config)->setTransport($this->transport);
    }

    /**
     * @return array<string, array{bool}>
     */
    public static function validationModes(): array
    {
        return ['validation on' => [true], 'validation off' => [false]];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeAddresses(): array
    {
        return [
            'CRLF header injection' => ["a@example.com\r\nBcc: attacker@example.test"],
            'LF only'               => ["a@example.com\nBcc: attacker@example.test"],
            'CR only'               => ["a@example.com\rBcc: attacker@example.test"],
            'NUL'                   => ["a@example.com\0.evil"],
        ];
    }

    /**
     * @dataProvider validationModes
     */
    public function testSetFromRejectsAnUnsafeAddressRegardlessOfValidate(bool $validate): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->mailer(['validate' => $validate])->setFrom("a@example.com\r\nBcc: attacker@example.test");
    }

    /**
     * @dataProvider validationModes
     */
    public function testSetFromRejectsAnUnsafeReturnPathRegardlessOfValidate(bool $validate): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->mailer(['validate' => $validate])
            ->setFrom('a@example.com', '', "bounce@example.com>\r\nBcc: attacker@example.test");
    }

    /**
     * @dataProvider validationModes
     */
    public function testSetReplyToRejectsAnUnsafeAddressRegardlessOfValidate(bool $validate): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->mailer(['validate' => $validate])->setReplyTo("a@example.com\r\nBcc: attacker@example.test");
    }

    /**
     * @dataProvider unsafeAddresses
     */
    public function testRecipientArraysRejectUnsafeAddressesWithValidationOff(string $address): void
    {
        foreach (['setTo', 'setCC', 'setBCC'] as $method) {
            try {
                $this->mailer(['validate' => false])->{$method}([$address]);
                self::fail($method . '() accepted an unsafe address.');
            } catch (InvalidAddressException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testRejectedRecipientLeavesThePreviousListUntouched(): void
    {
        $mailer = $this->mailer(['validate' => false])->setFrom('s@example.com')->setTo('ok@example.com');

        try {
            $mailer->setTo(["x@example.com\r\nBcc: attacker@example.test"]);
            self::fail('Expected an InvalidAddressException.');
        } catch (InvalidAddressException) {
            // Expected.
        }
        $mailer->send();

        self::assertSame(['ok@example.com'], $this->transport->last()->to);
    }

    public function testRecipientStringWithLineBreaksIsStillSplitIntoSeparateAddresses(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo("a@example.com\nb@example.com")->send();

        self::assertSame(['a@example.com', 'b@example.com'], $this->transport->last()->to);
    }

    public function testTrailingLineBreakOfARecipientIsTrimmedNotRejected(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo(["a@example.com\r\n"])->send();

        self::assertSame(['a@example.com'], $this->transport->last()->to);
    }

    public function testSetHeaderRejectsAnUnsafeName(): void
    {
        $this->expectException(MailerException::class);

        $this->mailer()->setHeader("X-Custom\r\nBcc: attacker@example.test", 'value');
    }

    public function testSetHeaderRejectsANulInTheName(): void
    {
        $this->expectException(MailerException::class);

        $this->mailer()->setHeader("X-Custom\0", 'value');
    }

    /**
     * The value keeps the pre-existing, documented behaviour: line breaks are
     * stripped rather than rejected, so a value with a trailing newline keeps
     * working. Either way no header can be injected.
     */
    public function testSetHeaderStillStripsLineBreaksAndNulFromTheValue(): void
    {
        $this->mailer(['validate' => false])
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setHeader('X-Custom', "value\r\nBcc: attacker@example.test\0\n")
            ->send();

        $value = $this->transport->last()->headers['X-Custom'];
        self::assertSame('valueBcc: attacker@example.test', $value);
    }

    public function testSubjectLineBreaksAreStrippedAndNulIsEncoded(): void
    {
        $this->mailer(['validate' => false])
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setSubject("Hello\r\nBcc: attacker@example.test")
            ->send();

        // Decoding shows what the header says: with the line breaks left in, the
        // Q-encoded value would decode to "Hello\r\nBcc: ...".
        $subject = $this->transport->last()->subject;
        self::assertSame('HelloBcc: attacker@example.test', iconv_mime_decode($subject, 0, 'UTF-8'));
    }

    public function testNulInTheSubjectIsEncodedNotWrittenRaw(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')->setSubject("Hel\0lo")->send();

        self::assertStringNotContainsString("\0", $this->transport->last()->subject);
    }

    public function testAnUnsafeConfiguredSenderIsRejectedAtSendTime(): void
    {
        $mailer = $this->mailer(['validate' => false, 'fromEmail' => "a@example.com\r\nBcc: attacker@example.test"])
            ->setTo('rcpt@example.com');

        $this->expectException(InvalidAddressException::class);

        $mailer->send();
    }

    /**
     * @return array<string, array{string, string, bool}>
     */
    public static function configuredSenders(): array
    {
        $cases = [
            'trailing LF'          => ["a@example.com\n", ''],
            'trailing CRLF CRLF'   => ["a@example.com\r\n\r\n", ''],
            'trailing NUL'         => ["a@example.com\0", ''],
        ];
        $out = [];
        foreach ($cases as $label => [$email, $name]) {
            $out[$label . ', validate on'] = [$email, $name, true];
            $out[$label . ', validate off'] = [$email, $name, false];
        }

        return $out;
    }

    /**
     * @dataProvider configuredSenders
     */
    public function testMessageIdIsBuiltFromTheParsedConfiguredSender(string $email, string $name, bool $validate): void
    {
        $mailer = $this->mailer(['fromEmail' => $email, 'fromName' => $name, 'validate' => $validate]);

        self::assertSame(1, preg_match('/\A<[^\r\n\0]+@example\.com>\z/', $mailer->getMessageID()));

        $mailer->setTo('rcpt@example.com')->send();
        $messageId = $this->transport->last()->headers['Message-ID'];
        self::assertSame(1, preg_match('/\A<[^\r\n\0]+@example\.com>\z/', $messageId));
    }

    /**
     * @dataProvider validationModes
     */
    public function testALineBreakHiddenInTheConfiguredSenderNameCannotReachTheMessageId(bool $validate): void
    {
        // The address part is "a@example.com"; the rest is split off as a display
        // name, which an explicit fromName replaces. Nothing of it may be written.
        $mailer = $this->mailer([
            'fromName'  => 'App',
            'fromEmail' => "x@y\rBcc: evil@z.com\rX: <a@example.com>",
            'validate'  => $validate,
        ]);

        self::assertSame(1, preg_match('/\A<[^\r\n\0]+@example\.com>\z/', $mailer->getMessageID()));

        $mailer->setTo('rcpt@example.com')->send();

        $headers = $this->transport->last()->headers;
        self::assertSame('"App" <a@example.com>', $headers['From']);
        self::assertSame(1, preg_match('/\A<[^\r\n\0]+@example\.com>\z/', $headers['Message-ID']));
        self::assertNotContains('evil@z.com', $headers);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function angleBracketAddresses(): array
    {
        return [
            'RCPT parameter' => ['v@x.com> NOTIFY=NEVER'],
            'MAIL parameter' => ['a@example.com> SIZE=1'],
            'opening bracket' => ['v@x.com <y'],
            'inner brackets' => ['v@x<y>.com'],
        ];
    }

    /**
     * @dataProvider angleBracketAddresses
     */
    public function testAnglesBracketsCannotAddSmtpParametersWithValidationOff(string $address): void
    {
        foreach (['setTo', 'setCC', 'setBCC'] as $method) {
            try {
                $this->mailer(['validate' => false])->{$method}([$address]);
                self::fail($method . '() accepted "' . $address . '".');
            } catch (InvalidAddressException) {
                $this->addToAssertionCount(1);
            }
        }

        try {
            $this->mailer(['validate' => false])->setFrom('s@example.com', '', $address);
            self::fail('setFrom() accepted the return path "' . $address . '".');
        } catch (InvalidAddressException) {
            $this->addToAssertionCount(1);
        }

        $this->expectException(InvalidAddressException::class);
        $this->mailer(['validate' => false])->setFrom($address);
    }

    public function testASurroundingAngleBracketPairStillWorks(): void
    {
        $this->mailer(['validate' => false])->setFrom('<s@example.com>')->setTo(['<a@example.com>'])->send();

        self::assertSame(['a@example.com'], $this->transport->last()->to);
    }

    public function testSetAltMessageStripsCarriageReturns(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')
            ->setMailType('html')->setMessage('<p>x</p>')->setAltMessage("one\r\ntwo\r.three\r")->send();

        self::assertStringNotContainsString("\r", $this->transport->last()->body);
        self::assertStringContainsString("one\ntwo.three", $this->transport->last()->body);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedHeaderNames(): array
    {
        return [
            'lower-case from'      => ['from'],
            'message-id'           => ['message-id'],
            'content-type'         => ['Content-Type'],
            'return-path'          => ['RETURN-PATH'],
            'x-mailer'             => ['x-mailer'],
        ];
    }

    /**
     * @dataProvider reservedHeaderNames
     */
    public function testReservedHeadersWinWhateverTheCaseOfTheName(string $name): void
    {
        $this->mailer()
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setHeader($name, 'evil@example.test')
            ->setHeader('X-Keep', 'kept')
            ->send();

        $headers = $this->transport->last()->headers;
        $lower = array_map('strtolower', array_keys($headers));
        self::assertSame(array_unique($lower), $lower, 'No header may appear twice.');
        self::assertNotContains('evil@example.test', $headers);
        self::assertSame('kept', $headers['X-Keep']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHeaderNames(): array
    {
        return [
            'only spaces'    => ['   '],
            'trailing LF'    => ["X-A\n"],
            'trailing CR'    => ["X-A\r"],
            'trailing tab'   => ["To\t"],
            'leading tab'    => ["\tTo"],
            'vertical tab'   => ["To\x0b"],
            'inner space'    => ['X Y'],
            'colon'          => ['X: y'],
            'trailing colon' => ['X-A:'],
            'tab'            => ["X\tY"],
            'empty'          => [''],
            'non-ASCII'      => ['X-Ünï'],
            'DEL'            => ["X\x7F"],
        ];
    }

    /**
     * @dataProvider invalidHeaderNames
     */
    public function testSetHeaderRejectsNamesThatAreNotFieldNames(string $name): void
    {
        $this->expectException(MailerException::class);

        $this->mailer()->setHeader($name, 'value');
    }

    public function testAQuoteInAnAttachmentNameCannotBreakOutOfTheParameter(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')
            ->attachContent('x', 'a"; filename="evil.exe', 'attachment', 'text/plain')->send();

        $body = $this->transport->last()->body;
        self::assertStringContainsString('name="a\\"; filename=\\"evil.exe"', $body);
    }

    public function testABoundaryFactoryCannotInjectHeaders(): void
    {
        $mailer = $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')
            ->attachContent('x', 'a.txt')
            ->setBoundaryFactory(static fn(string $prefix): string => "b\"\r\nBcc: e@z.com");

        $this->expectException(MailerException::class);

        $mailer->send();
    }

    public function testAnEmptyBoundaryIsRejected(): void
    {
        $mailer = $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')
            ->attachContent('x', 'a.txt')
            ->setBoundaryFactory(static fn(string $prefix): string => '');

        $this->expectException(MailerException::class);

        $mailer->send();
    }

    public function testACustomHeaderTheLibraryDoesNotWriteIsKept(): void
    {
        $this->mailer()
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setHeader('Cc', 'c@co.example')
            ->setHeader('Bcc', 'archive@co.example')
            ->setHeader('X-Keep', 'kept')
            ->send();

        $headers = $this->transport->last()->headers;
        self::assertSame('c@co.example', $headers['Cc']);
        self::assertSame('archive@co.example', $headers['Bcc']);
    }

    public function testACustomCcLosesToTheRealCcWhateverTheCase(): void
    {
        $this->mailer()
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setCC('real@example.com')
            ->setHeader('cc', 'archive@co.example')
            ->send();

        $headers = $this->transport->last()->headers;
        self::assertSame('real@example.com', $headers['Cc']);
        self::assertArrayNotHasKey('cc', $headers);
    }
}
