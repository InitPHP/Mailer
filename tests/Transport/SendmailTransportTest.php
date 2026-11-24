<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\Address;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\SendmailTransport;
use InitPHP\Mailer\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

final class SendmailTransportTest extends TestCase
{
    private string $command = '';

    private string $message = '';

    private function transport(int $status = 0): SendmailTransport
    {
        return new SendmailTransport(
            '/usr/sbin/sendmail',
            new EmailValidator(),
            function (string $command, string $message) use ($status): int {
                $this->command = $command;
                $this->message = $message;

                return $status;
            },
        );
    }

    private function email(): PreparedEmail
    {
        return new PreparedEmail(
            new Address('from@example.com'),
            ['to@example.com'],
            ['cc@example.com'],
            ['bcc@example.com'],
            'Subject line',
            ['From' => '<from@example.com>'],
            'Content-Type: text/plain; charset=UTF-8',
            'The body',
            "\n",
        );
    }

    public function testBuildsTheSendmailCommandWithTheEnvelopeSender(): void
    {
        $this->transport()->send($this->email());

        $this->assertSame('/usr/sbin/sendmail -oi -f from@example.com -t', $this->command);
    }

    public function testWritesHeadersAndBodyToThePipe(): void
    {
        $this->transport()->send($this->email());

        $this->assertStringContainsString('To: to@example.com', $this->message);
        $this->assertStringContainsString('Subject: Subject line', $this->message);
        $this->assertStringContainsString('Bcc: bcc@example.com', $this->message);
        $this->assertStringContainsString('Content-Type: text/plain', $this->message);
        $this->assertStringContainsString("\n\nThe body", $this->message);
    }

    public function testThrowsOnNonZeroExitStatus(): void
    {
        $this->expectException(TransportException::class);
        $this->expectExceptionCode(127);

        $this->transport(127)->send($this->email());
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
            $this->assertSame('', $this->command, 'Nothing may be piped to sendmail.');
        }
    }
}
