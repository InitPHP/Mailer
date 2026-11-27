<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Transport;

use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\InvalidAddressException;
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

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeRecipients(): array
    {
        return [
            'CRLF command injection' => ["rcpt@example.com>\r\nRSET\r\nMAIL FROM:<x@example.test"],
            'LF'                     => ["rcpt@example.com\nRSET"],
            'NUL'                    => ["rcpt@example.com\0"],
        ];
    }

    /**
     * @dataProvider unsafeRecipients
     */
    public function testRejectsAnUnsafeRecipientBeforeConnecting(string $recipient): void
    {
        $client = new FakeSocketClient([]);

        try {
            $this->transport($client)->send($this->email(to: ['ok@example.com'], bcc: [$recipient]));
            $this->fail('Expected an InvalidAddressException.');
        } catch (InvalidAddressException) {
            $this->assertSame([], $client->writes);
            $this->assertSame(0, $client->openCount);
        }
    }

    public function testRefusesAHostnameWithALineBreakBeforeConnecting(): void
    {
        $client = new FakeSocketClient(["220 hi\r\n"]);
        $transport = new SmtpTransport(
            $client,
            'mx.example.com',
            25,
            5,
            '',
            false,
            '',
            '',
            false,
            false,
            true,
            "host\r\nRSET",
            "\r\n",
        );

        try {
            $transport->send($this->email());
            $this->fail('Expected a TransportException.');
        } catch (TransportException) {
            $this->assertSame(0, $client->openCount, 'No connection may be left open.');
            $this->assertSame([], $client->writes);
        }
    }

    public function testAFailedStarttlsClosesTheConnectionAndTheNextSendNeverSendsAuthInTheClear(): void
    {
        $client = new FakeSocketClient([
            // First send: STARTTLS is refused.
            "220 hi\r\n", "250 ehlo\r\n", "454 TLS not available\r\n",
            // Second send: it must start over from the greeting.
            "220 hi\r\n", "250 ehlo\r\n", "454 TLS not available\r\n",
        ]);
        $transport = $this->transport($client, crypto: 'tls', auth: true, user: 'user', pass: 'secret');

        foreach ([1, 2] as $attempt) {
            try {
                $transport->send($this->email());
                $this->fail('Expected a TransportException on attempt ' . $attempt . '.');
            } catch (TransportException $e) {
                $this->assertSame(454, $e->getCode());
                $this->assertFalse($client->isOpen(), 'The connection must be closed after a failure.');
            }
        }

        $this->assertSame(2, $client->openCount);
        $this->assertSame(2, substr_count($client->conversation(), 'STARTTLS'));
        $this->assertStringNotContainsString('AUTH', $client->conversation());
        $this->assertStringNotContainsString(base64_encode('secret'), $client->conversation());
    }

    public function testAFailedTlsHandshakeClosesTheConnectionAndTheNextSendNeverSendsAuthInTheClear(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n", "250 ehlo\r\n", "220 go ahead\r\n",
            "220 hi\r\n", "250 ehlo\r\n", "220 go ahead\r\n",
        ]);
        $client->cryptoResult = false;
        $transport = $this->transport($client, crypto: 'tls', auth: true, user: 'user', pass: 'secret');

        foreach ([1, 2] as $attempt) {
            try {
                $transport->send($this->email());
                $this->fail('Expected a TransportException on attempt ' . $attempt . '.');
            } catch (TransportException) {
                $this->assertFalse($client->isOpen());
            }
        }

        $this->assertSame(2, $client->openCount);
        $this->assertStringNotContainsString('AUTH', $client->conversation());
    }

    public function testABadGreetingClosesTheConnection(): void
    {
        $client = new FakeSocketClient(["554 no service\r\n"]);

        try {
            $this->transport($client)->send($this->email());
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertSame(554, $e->getCode());
        }

        $this->assertFalse($client->isOpen());
        $this->assertSame([], $client->writes, 'Nothing may be sent to a server that did not greet us.');
    }

    public function testAFailedEhloClosesTheConnection(): void
    {
        $client = new FakeSocketClient(["220 hi\r\n", "502 not implemented\r\n"]);

        try {
            $this->transport($client)->send($this->email());
            $this->fail('Expected a TransportException.');
        } catch (TransportException) {
            $this->assertFalse($client->isOpen());
        }
    }

    public function testAFailedAuthClosesTheKeptAliveConnectionAndAuthenticatesAgain(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n", "250 ehlo\r\n", "334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "535 bad credentials\r\n",
            "220 hi\r\n", "250 ehlo\r\n", "334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "535 bad credentials\r\n",
        ]);
        $transport = $this->transport($client, auth: true, user: 'user', pass: 'wrong', keepAlive: true);

        foreach ([1, 2] as $attempt) {
            try {
                $transport->send($this->email());
                $this->fail('Expected a TransportException on attempt ' . $attempt . '.');
            } catch (TransportException $e) {
                $this->assertSame(535, $e->getCode());
                $this->assertFalse($client->isOpen());
            }
        }

        $this->assertSame(2, $client->openCount);
        $this->assertSame(2, substr_count($client->conversation(), 'AUTH LOGIN'));
    }

    public function testARejectedMessageClosesTheKeptAliveConnection(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n", "250 ehlo\r\n", "250 from\r\n", "250 rcpt\r\n", "354 data\r\n", "554 rejected\r\n",
        ]);

        try {
            $this->transport($client, keepAlive: true)->send($this->email());
            $this->fail('Expected a TransportException.');
        } catch (TransportException) {
            $this->assertFalse($client->isOpen());
        }
    }

    public function testDotStuffingFollowsABareCarriageReturnNewline(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r", "250 ehlo\r", "250 from\r", "250 rcpt\r", "354 data\r", "250 queued\r", "221 bye\r",
        ]);
        $transport = new SmtpTransport($client, 'mx.example.com', 25, 5, '', false, '', '', false, false, true, 'localhost', "\r");

        $email = new PreparedEmail(
            new Address('sender@example.com'),
            ['rcpt@example.com'],
            [],
            [],
            'Subject',
            ['From' => '<sender@example.com>'],
            'Content-Type: text/plain; charset=UTF-8',
            "first\r.second\r..third\r.",
            "\r",
        );
        $transport->send($email);

        $this->assertStringContainsString("first\r..second\r...third\r..\r.\r", $client->conversation());
    }

    public function testAKeptAliveConnectionAuthenticatesAgainAfterAFailureFollowingASuccessfulAuth(): void
    {
        $client = new FakeSocketClient([
            // First send: AUTH succeeds, MAIL FROM is rejected.
            "220 hi\r\n", "250 ehlo\r\n", "334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n", "550 no\r\n",
            // Second send: a new connection must authenticate again.
            "220 hi\r\n", "250 ehlo\r\n", "334 VXNlcm5hbWU6\r\n", "334 UGFzc3dvcmQ6\r\n", "235 ok\r\n", "550 no\r\n",
        ]);
        $transport = $this->transport($client, auth: true, user: 'user', pass: 'secret', keepAlive: true);

        foreach ([1, 2] as $attempt) {
            try {
                $transport->send($this->email());
                $this->fail('Expected a TransportException on attempt ' . $attempt . '.');
            } catch (TransportException $e) {
                $this->assertSame(550, $e->getCode());
            }
        }

        $this->assertSame(2, $client->openCount);
        $this->assertSame(2, substr_count($client->conversation(), 'AUTH LOGIN'));
    }

    public function testAFailedResetAfterAnAcceptedMessageDoesNotFailTheSend(): void
    {
        $client = new FakeSocketClient([
            // First send: the message is queued, then RSET is refused.
            "220 hi\r\n", "250 ehlo\r\n", "250 from\r\n", "250 rcpt\r\n", "354 data\r\n", "250 queued\r\n", "500 what\r\n",
            // Second send: a fresh session.
            "220 hi\r\n", "250 ehlo\r\n", "250 from\r\n", "250 rcpt\r\n", "354 data\r\n", "250 queued\r\n", "250 reset\r\n",
        ]);
        $transport = $this->transport($client, keepAlive: true);

        $transport->send($this->email());

        $this->assertFalse($client->isOpen());
        $this->assertSame(1, $client->openCount);

        $transport->send($this->email());

        $this->assertTrue($client->isOpen());
        $this->assertSame(2, $client->openCount);
        $this->assertSame(2, substr_count($client->conversation(), 'DATA'));
    }

    public function testARejectedAuthUsernameStepDoesNotLeakTheBase64UsernameInTheMessage(): void
    {
        $client = new FakeSocketClient(["220 hi\r\n", "250 ehlo\r\n", "334 VXNlcm5hbWU6\r\n", "535 no such user\r\n"]);
        $transport = $this->transport($client, auth: true, user: 'user@example.com', pass: 'secret');

        try {
            $transport->send($this->email());
            $this->fail('Expected a TransportException.');
        } catch (TransportException $e) {
            $this->assertSame(535, $e->getCode());
            $this->assertStringContainsString('username step was rejected', $e->getMessage());
            $this->assertStringContainsString('no such user', $e->getMessage());
            $this->assertStringNotContainsString(base64_encode('user@example.com'), $e->getMessage());
            $this->assertFalse($client->isOpen());
        }
    }

    public function testTheOrcptParameterIsXtextEncodedSoAnAddressCannotAddParameters(): void
    {
        $client = new FakeSocketClient([
            "220 hi\r\n", "250 ehlo\r\n", "250 from\r\n", "250 rcpt\r\n", "354 data\r\n", "250 queued\r\n", "221 bye\r\n",
        ]);

        $this->transport($client, dsn: true)->send($this->email(to: ['v@x.com NOTIFY=NEVER+a=b']));

        $this->assertStringContainsString(
            'ORCPT=rfc822;v@x.com+20NOTIFY+3DNEVER+2Ba+3Db' . "\r\n",
            $client->conversation(),
        );
        $this->assertStringNotContainsString('ORCPT=rfc822;v@x.com NOTIFY', $client->conversation());
    }
}
