<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

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
}
