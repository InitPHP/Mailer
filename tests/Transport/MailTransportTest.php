<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\Address;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\MailTransport;
use InitPHP\Mailer\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

final class MailTransportTest extends TestCase
{
    /** @var array<string, string> */
    private array $captured = [];

    private function transport(bool $succeeds = true): MailTransport
    {
        return new MailTransport(
            new EmailValidator(),
            function (string $to, string $subject, string $message, string $headers, string $params) use ($succeeds): bool {
                $this->captured = [
                    'to'      => $to,
                    'subject' => $subject,
                    'message' => $message,
                    'headers' => $headers,
                    'params'  => $params,
                ];

                return $succeeds;
            },
        );
    }

    private function email(): PreparedEmail
    {
        return new PreparedEmail(
            new Address('from@example.com'),
            ['a@example.com', 'b@example.com'],
            [],
            ['hidden@example.com'],
            'Hi there',
            ['From' => '<from@example.com>', 'Date' => 'today'],
            'Content-Type: text/plain; charset=UTF-8',
            'Body text',
            "\n",
        );
    }

    public function testPassesToAndSubjectAsDedicatedArguments(): void
    {
        $this->transport()->send($this->email());

        $this->assertSame('a@example.com, b@example.com', $this->captured['to']);
        $this->assertSame('Hi there', $this->captured['subject']);
        $this->assertSame('Body text', $this->captured['message']);
    }

    public function testKeepsToAndSubjectOutOfTheHeaderBlock(): void
    {
        $this->transport()->send($this->email());

        $this->assertStringContainsString('From: <from@example.com>', $this->captured['headers']);
        $this->assertStringContainsString('Content-Type: text/plain', $this->captured['headers']);
        $this->assertStringNotContainsString('To:', $this->captured['headers']);
        $this->assertStringNotContainsString('Subject:', $this->captured['headers']);
    }

    public function testIncludesBccInTheHeaderBlock(): void
    {
        $this->transport()->send($this->email());

        $this->assertStringContainsString('Bcc: hidden@example.com', $this->captured['headers']);
    }

    public function testSetsTheEnvelopeSenderWhenShellSafe(): void
    {
        $this->transport()->send($this->email());

        $this->assertSame('-f from@example.com', $this->captured['params']);
    }

    public function testThrowsWhenMailReturnsFalse(): void
    {
        $this->expectException(TransportException::class);

        $this->transport(false)->send($this->email());
    }

    /**
     * @return array<string, array{list<string>, string, array<string, string>, 3?: list<string>}>
     */
    public static function unsafeEmails(): array
    {
        return [
            'recipient with a line break' => [["t@x.com\nBcc: e@z.com"], 'Subject', ['X-A' => 'v']],
            'recipient with an angle bracket' => [['t@x.com> NOTIFY=NEVER'], 'Subject', ['X-A' => 'v']],
            'bcc with an angle bracket' => [['t@x.com'], 'Subject', ['X-A' => 'v'], ['b@x.com> NOTIFY=NEVER']],
            'bcc with a NUL' => [['t@x.com'], 'Subject', ['X-A' => 'v'], ["b@x.com\0x"]],
            'subject with a line break'   => [['t@x.com'], "s\nX-Inj: 1", ['X-A' => 'v']],
            'header value with a line break' => [['t@x.com'], 'Subject', ['X-A' => "v\nX-B: 1"]],
            'header name with a line break'  => [['t@x.com'], 'Subject', ["X-A\nX-B" => 'v']],
        ];
    }

    /**
     * @dataProvider unsafeEmails
     *
     * @param list<string>          $to
     * @param array<string, string> $headers
     * @param list<string>          $bcc
     */
    public function testRefusesAnEmailBuiltByHandWithInjectableFields(array $to, string $subject, array $headers, array $bcc = []): void
    {
        $email = new PreparedEmail(
            new Address('from@example.com'),
            $to,
            [],
            $bcc,
            $subject,
            $headers,
            'Content-Type: text/plain; charset=UTF-8',
            'Body',
            "\n",
        );

        try {
            $this->transport()->send($email);
            $this->fail('Expected the transport to refuse the message.');
        } catch (MailerException) {
            $this->assertSame([], $this->captured, 'mail() must not be called.');
        }
    }

    public function testAFoldedSubjectIsAccepted(): void
    {
        $email = new PreparedEmail(
            new Address('from@example.com'),
            ['a@example.com'],
            [],
            [],
            "=?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?=",
            ['From' => '<from@example.com>'],
            'Content-Type: text/plain; charset=UTF-8',
            'Body',
            "\n",
        );

        $this->transport()->send($email);

        $this->assertSame("=?UTF-8?Q?a?=\r\n =?UTF-8?Q?b?=", $this->captured['subject']);
    }

    public function testACustomSubjectHeaderIsDroppedBecauseSubjectIsAMailArgument(): void
    {
        $email = new PreparedEmail(
            new Address('from@example.com'),
            ['a@example.com'],
            [],
            [],
            'Real subject',
            ['From' => '<from@example.com>', 'subject' => 'Evil subject', 'SUBJECT' => 'Evil too'],
            'Content-Type: text/plain; charset=UTF-8',
            'Body',
            "\n",
        );

        $this->transport()->send($email);

        $this->assertSame('Real subject', $this->captured['subject']);
        $this->assertStringNotContainsString('ubject', $this->captured['headers']);
        $this->assertStringNotContainsString('Evil', $this->captured['headers']);
    }
}
