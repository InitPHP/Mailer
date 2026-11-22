# Testing

You can unit-test your mail code without a live mail server. Every side effect —
the SMTP socket, the `mail()` function, the sendmail pipe, even the MIME boundary
generator — is injectable.

## Capture messages with a fake transport

The simplest approach: implement `TransportInterface` to record the
`PreparedEmail` instead of sending it, and inject it with `setTransport()`.

```php
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Transport\TransportInterface;

final class RecordingTransport implements TransportInterface
{
    /** @var list<PreparedEmail> */
    public array $sent = [];

    public function send(PreparedEmail $email): void
    {
        $this->sent[] = $email;
    }
}
```

```php
use InitPHP\Mailer\Mailer;

$transport = new RecordingTransport();
$mailer = Mailer::newInstance()->setTransport($transport);

$mailer->setFrom('you@example.com', 'You')
    ->setTo('recipient@example.com')
    ->setSubject('Hi')
    ->setMessage('Body')
    ->send();

$email = $transport->sent[0];
$this->assertSame('you@example.com', $email->from->getEmail());
$this->assertSame(['recipient@example.com'], $email->to);
$this->assertSame('you@example.com', $email->headers['X-Sender']);
```

A `PreparedEmail` exposes the structured message: `from`, `to`, `cc`, `bcc`,
`subject`, `headers`, `contentHeaders`, `body` and `newline`.

## Deterministic MIME boundaries

MIME boundaries are random by default, which makes byte-for-byte assertions hard.
Inject a deterministic generator with `setBoundaryFactory()`:

```php
$counter = 0;
$mailer->setBoundaryFactory(fn (string $prefix) => $prefix . (++$counter));

$mailer->setMailType('html')
    ->setFrom('you@example.com')->setTo('to@example.com')
    ->setMessage('<p>Hi</p>')->send();

// boundaries are now B_ALT_1, B_ATC_2, … — stable across runs.
```

## The `mail()` transport without sending

`MailTransport` takes the mail function as a constructor argument, so you can
substitute a recorder:

```php
use InitPHP\Mailer\Transport\MailTransport;
use InitPHP\Mailer\Validation\EmailValidator;

$captured = [];
$transport = new MailTransport(
    new EmailValidator(),
    function (string $to, string $subject, string $message, string $headers, string $params) use (&$captured): bool {
        $captured = compact('to', 'subject', 'message', 'headers', 'params');
        return true; // pretend it succeeded
    },
);

$mailer = Mailer::newInstance()->setTransport($transport);
$mailer->setFrom('you@example.com')->setTo('to@example.com')->setSubject('Hi')->setMessage('Body')->send();

// assert against $captured['to'], $captured['headers'], …
```

`SendmailTransport` similarly takes a `callable(string $command, string $message): int`
returning the exit status, so it can be driven without spawning a process.

## SMTP without a server

`SmtpTransport` does all I/O through `SocketClientInterface`. Provide a fake that
replays scripted server replies and records what was written:

```php
use InitPHP\Mailer\Transport\Smtp\SocketClientInterface;

final class FakeSocketClient implements SocketClientInterface
{
    /** @var list<string> */ public array $writes = [];
    public bool $cryptoEnabled = false;
    private bool $open = false;
    /** @param list<string> $replies */
    public function __construct(private array $replies) {}

    public function open(string $host, int $port, int $timeout): void { $this->open = true; }
    public function isOpen(): bool { return $this->open; }
    public function write(string $bytes): void { $this->writes[] = $bytes; }
    public function readLine(int $length = 512): string|false { return array_shift($this->replies) ?? false; }
    public function enableCrypto(int $cryptoMethod): bool { $this->cryptoEnabled = true; return true; }
    public function close(): void { $this->open = false; }
}
```

```php
use InitPHP\Mailer\Transport\SmtpTransport;

$client = new FakeSocketClient([
    "220 mx ESMTP\r\n", "250 ok\r\n", "250 from\r\n",
    "250 rcpt\r\n", "354 data\r\n", "250 queued\r\n", "221 bye\r\n",
]);

$transport = new SmtpTransport(
    $client, 'mx.example.com', 25, 5, '', false, '', '', false, false, true, 'localhost', "\r\n",
);
$transport->send($preparedEmail);

$conversation = implode('', $client->writes);
$this->assertStringContainsString('MAIL FROM:<sender@example.com>', $conversation);
$this->assertStringContainsString('RCPT TO:<recipient@example.com>', $conversation);
```

## How the library tests itself

This is exactly how `initphp/mailer`'s own suite works — the encoders, MIME
builder, validators and value objects are tested in isolation, while the three
transports are tested through these injected fakes. No test touches the network,
so the suite runs in milliseconds and is reproducible everywhere.
