# InitPHP Mailer — Documentation

InitPHP Mailer composes an e-mail message through a fluent API and hands it to a
transport — PHP's native `mail()`, a local `sendmail` binary, or SMTP. It builds
MIME multipart bodies, encodes headers per RFC 2047, encodes bodies as
quoted-printable, and attaches files or inline images.

## Contents

| Guide | What it covers |
| ----- | -------------- |
| [Getting started](getting-started.md) | Install, build your first message, send it. |
| [Configuration](configuration.md) | Every configuration key and its default. |
| [Sending mail](sending-mail.md) | Recipients, CC/BCC, BCC batching, HTML and alternatives. |
| [Attachments](attachments.md) | Files, in-memory content and inline (CID) images. |
| [SMTP transport](smtp-transport.md) | Host, port, encryption, authentication, keep-alive, DSN. |
| [Encoding & headers](encoding-and-headers.md) | Charset, Q-encoding, quoted-printable, word wrap, custom headers. |
| [Exceptions](exceptions.md) | What is thrown, when, and how to catch it. |
| [Facade](facade.md) | The static facade over a shared instance. |

## At a glance

```php
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Exception\MailerException;

$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
]);

try {
    $mailer->setFrom('you@example.com', 'Your Name')
        ->setTo('recipient@example.com')
        ->setSubject('Hello')
        ->setMessage('Plain-text body')
        ->send();
} catch (MailerException $e) {
    // handle failure
}
```

## Core concepts

- **`Mailer`** — the object you build a message on and call `send()` against.
  Create it with `Mailer::newInstance($config)` or `new Mailer($config)`.
- **Configuration** — an array passed once at construction. It selects the
  transport and its options and provides message defaults (charset, priority…).
- **Transport** — the delivery backend chosen by the `protocol` option:
  `mail`, `sendmail` or `smtp`. You never construct it directly.
- **Exceptions** — failures throw; there are no boolean return values to check.

## Requirements

- PHP 8.1 or newer
- `ext-mbstring`, `ext-iconv`, `ext-fileinfo`
