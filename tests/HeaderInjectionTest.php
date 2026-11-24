<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

use function str_contains;

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
    public function testSetFromRejectsAnUnsafeNameRegardlessOfValidate(bool $validate): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->mailer(['validate' => $validate])->setFrom('a@example.com', "Name\r\nBcc: attacker@example.test");
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

    public function testSubjectLineBreaksAndNulAreStrippedFromTheHeader(): void
    {
        $this->mailer(['validate' => false])
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setSubject("Hello\r\nBcc: attacker@example.test\0")
            ->send();

        $subject = $this->transport->last()->subject;
        self::assertStringNotContainsString("\0", $subject);
        self::assertStringNotContainsString("\nBcc", $subject);
        self::assertFalse(str_contains($subject, "\r\nBcc"));
    }

    public function testAnUnsafeConfiguredSenderIsRejectedAtSendTime(): void
    {
        $mailer = $this->mailer(['validate' => false, 'fromEmail' => "a@example.com\r\nBcc: attacker@example.test"])
            ->setTo('rcpt@example.com');

        $this->expectException(InvalidAddressException::class);

        $mailer->send();
    }
}
