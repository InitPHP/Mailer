# InitPHP Mailer — Wiki

Welcome to the official documentation for **`initphp/mailer`** — a small,
dependency-free PHP mailer. You compose a message with a fluent API and send it
through PHP's native `mail()`, a local `sendmail` binary, or SMTP, with
attachments, inline images, HTML + plain-text multipart bodies and RFC-compliant
header encoding.

```bash
composer require initphp/mailer
```

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
        ->setSubject('Hello from InitPHP Mailer')
        ->setMessage('This is a plain-text message.')
        ->send();
} catch (MailerException $e) {
    // $e->getMessage(); for SMTP failures $e->getCode() holds the reply code.
}
```

## What it does

| Capability | Where to read |
| --- | --- |
| Send via `mail()`, `sendmail` or SMTP | [Transports](Transports.md) · [SMTP Transport](SMTP-Transport.md) |
| Configure once with an array | [Configuration](Configuration.md) |
| To / CC / BCC, reply-to, BCC batching | [Sending Mail](Sending-Mail.md) |
| HTML bodies with a plain-text alternative | [HTML Messages](HTML-Messages.md) |
| File, in-memory and inline (CID) attachments | [Attachments](Attachments.md) |
| Charset, Q-encoding, quoted-printable, word wrap, custom headers | [Encoding & Headers](Encoding-and-Headers.md) |
| Exception-based error handling | [Error Handling](Error-Handling.md) |
| Unit-test your mail code without a server | [Testing](Testing.md) |
| Every public method | [API Reference](API-Reference.md) |

## Requirements

- **PHP 8.2+**
- `ext-mbstring`, `ext-iconv`, `ext-fileinfo`

## Highlights

- **Three transports, one API** — switch between `mail`, `sendmail` and SMTP by
  changing a single config value; the message-building code never changes.
- **Correct MIME** — multipart/alternative for HTML, multipart/mixed for
  attachments, and multipart/related for inline images, nested correctly.
- **RFC-compliant encoding** — non-ASCII subjects and names are RFC 2047
  Q-encoded; HTML bodies are quoted-printable encoded.
- **Fails loudly** — invalid input is rejected the moment you supply it, and
  delivery failures throw with the SMTP reply code attached.
- **Testable by design** — every side effect (the socket, `mail()`, the
  sendmail pipe) is injectable, so you can unit-test without a live mail server.
- **Typed & analysed** — fully typed and checked at PHPStan's max level.

## Where to go next

New here? Start with [Installation](Installation.md) and the
[Quick Start](Quick-Start.md). Coming from 1.x? Read
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md).
