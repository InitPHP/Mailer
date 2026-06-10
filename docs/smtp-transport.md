# SMTP transport

Select SMTP with `'protocol' => 'smtp'` and configure the server:

```php
$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
]);
```

## Encryption

| `SMTPCrypto` | `SMTPPort` (typical) | Behaviour |
| ------------ | -------------------- | --------- |
| `'tls'` | 587 | Connect in clear text, then upgrade with `STARTTLS`. |
| `'ssl'` | 465 | Connect over TLS immediately (implicit TLS). |
| `''` | 25 | No encryption. |

Port 465 always uses implicit TLS regardless of `SMTPCrypto`. TLS is negotiated
with `STREAM_CRYPTO_METHOD_TLS_CLIENT`, so the highest protocol both ends support
is used.

## Authentication

When both `SMTPUser` and `SMTPPass` are set, `SMTPAuth` defaults to `true` and
`AUTH LOGIN` is performed. Set `SMTPAuth` explicitly to override. If the server
reports it is already authenticated (`503`), authentication is skipped.

## Keep-alive

With `'SMTPKeepAlive' => true` the connection stays open between messages (an
`RSET` is issued instead of `QUIT`), which avoids reconnecting for each message.
The connection is closed when the mailer is destroyed.

```php
$mailer = Mailer::newInstance(['protocol' => 'smtp', 'SMTPKeepAlive' => true, /* … */]);

foreach ($recipients as $to) {
    $mailer->setFrom('you@example.com')->setTo($to)->setSubject('Hi')->setMessage('…')->send();
}
```

## Delivery Status Notifications

`'DSN' => true` adds `NOTIFY=SUCCESS,DELAY,FAILURE` and an `ORCPT` to each
`RCPT TO`, if the server supports DSN.

## Errors

Any unexpected SMTP reply throws `TransportException`, with the server's reply
code available from `getCode()`:

```php
use InitPHP\Mailer\Exception\TransportException;

try {
    $mailer->setFrom('you@example.com')->setTo('user@example.com')->setMessage('…')->send();
} catch (TransportException $e) {
    error_log(sprintf('SMTP %d: %s', $e->getCode(), $e->getMessage()));
}
```

An empty `SMTPHost` throws `ConfigurationException` instead.

## Testing without a server

The SMTP transport talks over a `SocketClientInterface`. The default
implementation uses a real socket, but you can implement the interface (or use a
recording fake) to drive the conversation in tests without a live server — this
is exactly how the library's own SMTP tests work.
