<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

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
}
