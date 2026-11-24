# SMTP Transport

Select SMTP with `'protocol' => 'smtp'` and configure the server:

```php
use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
]);

$mailer->setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Hello over SMTP')
    ->setMessage('Sent through an SMTP server.')
    ->send();
```

## The conversation

On `send()` the transport:

1. opens the socket and reads the server greeting,
2. sends `EHLO`/`HELO` (`EHLO` when authenticating or using 8-bit encoding),
3. upgrades to TLS with `STARTTLS` when `SMTPCrypto` is `tls`, then `EHLO`s again,
4. performs `AUTH LOGIN` when authentication is enabled,
5. sends `MAIL FROM`, one `RCPT TO` per recipient (To + CC + BCC), then `DATA`,
6. writes the headers and body (with dot-stuffing), terminated by `.`,
7. sends `QUIT` and closes — or `RSET` when keep-alive is on.

## Encryption

| `SMTPCrypto` | Typical `SMTPPort` | Behaviour |
| --- | --- | --- |
| `'tls'` | 587 | Connect in clear text, then upgrade with `STARTTLS`. |
| `'ssl'` | 465 | Connect over TLS immediately (implicit TLS). |
| `''` | 25 | No encryption. |

Port **465 always uses implicit TLS**, regardless of `SMTPCrypto`. TLS is
negotiated with `STREAM_CRYPTO_METHOD_TLS_CLIENT`, so the highest protocol
version both ends support is chosen.

```php
// Implicit TLS (SMTPS)
$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPPort'   => 465,
    'SMTPCrypto' => 'ssl',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
]);
```

## Authentication

When both `SMTPUser` and `SMTPPass` are set, `SMTPAuth` defaults to `true` and
`AUTH LOGIN` is performed (username and password are base64-encoded). To force it
on or off, set `SMTPAuth` explicitly. If the server reports it is already
authenticated (`503`), the step is skipped.

```php
$mailer = Mailer::newInstance([
    'protocol' => 'smtp',
    'SMTPHost' => 'smtp.example.com',
    'SMTPUser' => 'you@example.com',
    'SMTPPass' => 'secret',
    'SMTPAuth' => true, // optional; derived from user+pass anyway
]);
```

## Keep-alive

With `'SMTPKeepAlive' => true` the connection is reused between messages — an
`RSET` is sent instead of `QUIT`, and authentication happens only once. This
avoids the connect/handshake cost when sending many messages in a loop. The
connection is closed when the mailer is garbage-collected.

If a send fails at any point — the greeting or `EHLO` is refused, `STARTTLS` is
refused or the TLS handshake fails, authentication or a recipient is rejected,
or the message itself is rejected — the connection is closed and the
"authenticated" state is forgotten. The next `send()` opens a new connection and
starts again from the greeting, `STARTTLS` and `AUTH`, so credentials are never
sent over a connection that did not finish upgrading to TLS.

```php
$mailer = Mailer::newInstance([
    'protocol'      => 'smtp',
    'SMTPHost'      => 'smtp.example.com',
    'SMTPUser'      => 'you@example.com',
    'SMTPPass'      => 'secret',
    'SMTPKeepAlive' => true,
]);

foreach ($recipients as $to) {
    $mailer->setFrom('you@example.com')
        ->setTo($to)
        ->setSubject('Newsletter')
        ->setMessage('…')
        ->send(); // same connection reused
}
```

## Delivery Status Notifications (DSN)

`'DSN' => true` appends `NOTIFY=SUCCESS,DELAY,FAILURE` and an `ORCPT` to each
`RCPT TO`, asking the server for delivery notifications (if it supports DSN). The
address in `ORCPT` is encoded as RFC 3461 `xtext` (a space, `+`, `=` or any byte
outside `!`..`~` becomes `+XX`), so an address cannot add parameters.

## Timeouts

`SMTPTimeout` (seconds, default 5) is applied both to the initial connection and
to socket reads/writes.

## Errors

| Situation | Exception |
| --- | --- |
| Empty `SMTPHost` | `ConfigurationException` |
| Auth enabled but no username/password | `ConfigurationException` |
| Connection refused / cannot open socket | `TransportException` |
| Unexpected SMTP reply (e.g. `550`, `535`) | `TransportException` (code = reply code) |
| The server's greeting is not `220` | `TransportException` (code = reply code) |
| TLS negotiation fails, or bytes arrived in clear text together with the `STARTTLS` reply | `TransportException` |
| A recipient contains a CR, LF, NUL, `<` or `>`, or the client hostname contains a CR, LF or NUL | `InvalidAddressException` / `TransportException`, before connecting |

```php
use InitPHP\Mailer\Exception\TransportException;

try {
    $mailer->setFrom('you@example.com')->setTo('user@example.com')->setMessage('…')->send();
} catch (TransportException $e) {
    error_log(sprintf('SMTP %d: %s', $e->getCode(), $e->getMessage()));
}
```

## Testing against a fake server

The transport does all I/O through `InitPHP\Mailer\Transport\Smtp\SocketClientInterface`:

```php
interface SocketClientInterface
{
    public function open(string $host, int $port, int $timeout): void;
    public function isOpen(): bool;
    public function write(string $bytes): void;
    public function readLine(int $length = 512): string|false;
    public function enableCrypto(int $cryptoMethod): bool;
    public function close(): void;
}
```

The default implementation (`StreamSocketClient`) uses a real socket, but you can
provide a fake that replays scripted replies and records the commands written —
which is exactly how the library's own SMTP tests run, with no live server. See
[Testing](Testing.md).
