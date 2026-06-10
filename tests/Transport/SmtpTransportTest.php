<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\TransportException;
use InitPHP\Mailer\Message\Address;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Tests\Support\FakeSocketClient;
use InitPHP\Mailer\Transport\SmtpTransport;
use PHPUnit\Framework\TestCase;

use function base64_encode;
use function substr_count;

final class SmtpTransportTest extends TestCase
{
    private function transport(
        FakeSocketClient $client,
        string $crypto = '',
        bool $auth = false,
        string $user = '',
        string $pass = '',
        bool $keepAlive = false,
        bool $dsn = false,
    ): SmtpTransport {
        return new SmtpTransport(
            $client,
            'mx.example.com',
            25,
            5,
            $crypto,
            $auth,
            $user,
            $pass,
            $keepAlive,
            $dsn,
            true,
            'localhost',
            "\r\n",
        );
    }

    /**
     * @param list<string> $to
     * @param list<string> $cc
     * @param list<string> $bcc
     */
    private function email(array $to = ['rcpt@example.com'], array $cc = [], array $bcc = [], string $body = 'Body'): PreparedEmail
    {
        return new PreparedEmail(
            new Address('sender@example.com'),
            $to,
            $cc,
            $bcc,
            'Subject',
            ['From' => '<sender@example.com>'],
            'Content-Type: text/plain; charset=UTF-8',
            $body,
            "\r\n",
        );
    }

    public function testSuccessfulConversationAndCleanQuit(): void
    {
        $client = new FakeSocketClient([
            "220 mx.example.com ESMTP\r\n",
            "250 mx.example.com\r\n",
            "250 OK\r\n",
            "250 OK\r\n",
            "354 End data with <CRLF>.<CRLF>\r\n",
            "250 2.0.0 Queued\r\n",
            "221 Bye\r\n",
        ]);

        $this->transport($client)->send($this->email());

        $conversation = $client->conversation();
        $this->assertStringContainsString('EHLO localhost', $conversation);
        $this->assertStringContainsString('MAIL FROM:<sender@example.com>', $conversation);
        $this->assertStringContainsString('RCPT TO:<rcpt@example.com>', $conversation);
        $this->assertStringContainsString('DATA', $conversation);
        $this->assertStringContainsString('QUIT', $conversation);
        // Regression: the QUIT response is no longer treated as a failure and
        // the socket is closed exactly once.
        $this->assertSame(1, $client->closeCount);
    }

    public function testSendsRecipientsForToCcAndBcc(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n",
            "250 ehlo\r\n",
            "250 from\r\n",
            "250 to\r\n",
            "250 cc\r\n",
            "250 bcc\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ]);

        $this->transport($client)->send($this->email(['to@example.com'], ['cc@example.com'], ['bcc@example.com']));

        $conversation = $client->conversation();
        $this->assertStringContainsString('RCPT TO:<to@example.com>', $conversation);
        $this->assertStringContainsString('RCPT TO:<cc@example.com>', $conversation);
        $this->assertStringContainsString('RCPT TO:<bcc@example.com>', $conversation);
        // Bcc must never appear in the transmitted header block.
        $this->assertStringNotContainsString('Bcc:', $conversation);
    }

    public function testAuthLogin(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n",
            "250 ehlo\r\n",
            "334 VXNlcm5hbWU6\r\n",
            "334 UGFzc3dvcmQ6\r\n",
            "235 2.7.0 Accepted\r\n",
            "250 from\r\n",
            "250 to\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ]);

        $this->transport($client, auth: true, user: 'user', pass: 'pass')->send($this->email());

        $conversation = $client->conversation();
        $this->assertStringContainsString('AUTH LOGIN', $conversation);
        $this->assertStringContainsString(base64_encode('user'), $conversation);
        $this->assertStringContainsString(base64_encode('pass'), $conversation);
    }

    public function testStartTlsNegotiation(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n",
            "250 ehlo-1\r\n",
            "220 ready to start tls\r\n",
            "250 ehlo-2\r\n",
            "250 from\r\n",
            "250 to\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ]);

        $this->transport($client, crypto: 'tls')->send($this->email());

        $this->assertTrue($client->cryptoEnabled);
        $this->assertStringContainsString('STARTTLS', $client->conversation());
        $this->assertSame(2, substr_count($client->conversation(), 'EHLO localhost'));
    }

    public function testDotStuffing(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n",
            "250 ehlo\r\n",
            "250 from\r\n",
            "250 to\r\n",
            "354 data\r\n",
            "250 queued\r\n",
            "221 bye\r\n",
        ]);

        $this->transport($client)->send($this->email(body: '.dangerous leading dot'));

        $this->assertStringContainsString('..dangerous leading dot', $client->conversation());
    }

    public function testThrowsWhenTheServerRejectsTheSender(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n",
            "250 ehlo\r\n",
            "550 5.1.0 Sender rejected\r\n",
        ]);

        $this->expectException(TransportException::class);
        $this->expectExceptionCode(550);

        $this->transport($client)->send($this->email());
    }

    public function testThrowsWhenHostIsNotConfigured(): void
    {
        $client = new FakeSocketClient([]);
        $transport = new SmtpTransport($client, '', 25, 5, '', false, '', '', false, false, true, 'localhost', "\r\n");

        $this->expectException(ConfigurationException::class);

        $transport->send($this->email());
    }
}
