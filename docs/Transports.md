# Transports

A *transport* is the backend that actually hands your message off for delivery.
You never construct one directly — you choose it with the `protocol`
configuration key (or [`setProtocol()`](API-Reference.md)), and the mailer builds
the right transport for you.

```php
$mailer = Mailer::newInstance(['protocol' => 'smtp', /* … */]);
// or:
$mailer->setProtocol('sendmail');
```

| `protocol` | Class | Uses | Good for |
| --- | --- | --- | --- |
| `'mail'` (default) | `MailTransport` | PHP's `mail()` | Servers with a configured MTA. |
| `'sendmail'` | `SendmailTransport` | a local sendmail binary | Direct control over the sendmail invocation. |
| `'smtp'` | `SmtpTransport` | a TCP socket to an SMTP server | Sending through an external provider. |

All three implement `InitPHP\Mailer\Transport\TransportInterface`:

```php
interface TransportInterface
{
    public function send(PreparedEmail $email): void; // throws TransportException
}
```

They never return a status — a failure throws
[`TransportException`](Error-Handling.md).

## `mail` — the native function

The default. The mailer hands `To` and `Subject` to `mail()` as its dedicated
arguments and passes everything else (including the MIME content headers) as the
additional headers. When the sender address is shell-safe it is also passed as
the `-f` envelope sender.

```php
$mailer = Mailer::newInstance(); // protocol 'mail'
$mailer->setFrom('you@example.com')->setTo('to@example.com')->setMessage('Hi')->send();
```

Delivery depends entirely on how `mail()` / the MTA is configured on the host.
`mail()` returning `false` becomes a `TransportException`.

## `sendmail` — a local binary

Pipes the full message to a sendmail-compatible binary (`mailPath`, default
`/usr/sbin/sendmail`) invoked with `-oi -t` (and `-f <sender>` when the sender is
shell-safe).

```php
$mailer = Mailer::newInstance([
    'protocol' => 'sendmail',
    'mailPath' => '/usr/sbin/sendmail',
]);
```

A non-zero exit status from the binary becomes a `TransportException`, with the
status available from `getCode()`.

## `smtp` — an SMTP server

Speaks SMTP over a socket: greeting, `EHLO`/`HELO`, optional STARTTLS, optional
`AUTH LOGIN`, `MAIL FROM`, `RCPT TO` for every recipient, `DATA`, then `QUIT`
(or `RSET` when keep-alive is on). It has its own page:
[SMTP Transport](SMTP-Transport.md).

```php
$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
]);
```

## Recipients on the wire

How To / CC / BCC are placed differs by transport, but you don't have to think
about it — set them with [`setTo()` / `setCC()` / `setBCC()`](Sending-Mail.md) and
each transport does the right thing:

- **`mail`** — `To` is a function argument; `Cc` and `Bcc` go in the headers.
- **`sendmail`** — `To`, `Cc` and `Bcc` go in the headers; the MTA strips `Bcc`
  thanks to `-t`.
- **`smtp`** — `To` and `Cc` are visible headers; every recipient (including
  BCC) is delivered with `RCPT TO`, and the **recipients you set with `setBCC()`
  never appear in the headers** (a `Bcc` header you add by hand with
  `setHeader()` does; see [Sending Mail](Sending-Mail.md#custom-headers)).

A `To`, `Cc` or `Bcc` header you add by hand with `setHeader()` is not a recipient
on `smtp`, but `mail()` and `sendmail` read recipients from the headers, so there
it can be one. A custom `To` is never replaced case-insensitively: it behaves as it
always did on every transport. See
[Custom headers](Sending-Mail.md#custom-headers) before passing untrusted input.

## Supplying your own transport

Any object implementing `TransportInterface` can be injected with
[`setTransport()`](API-Reference.md), overriding the configured protocol. This is
the primary seam used in [Testing](Testing.md), but it also lets you plug in a
custom backend (a logging transport, a queue, an API client, …).

```php
$mailer->setTransport($myCustomTransport);
```
