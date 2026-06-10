# InitPHP Mailer

A small, dependency-free PHP mailer. Compose a message with a fluent API and
send it through PHP's native `mail()`, a local `sendmail` binary, or SMTP — with
attachments, inline images, HTML + plain-text multipart bodies and RFC-compliant
header encoding.

[![Latest Stable Version](http://poser.pugx.org/initphp/mailer/v)](https://packagist.org/packages/initphp/mailer) [![Total Downloads](http://poser.pugx.org/initphp/mailer/downloads)](https://packagist.org/packages/initphp/mailer) [![License](http://poser.pugx.org/initphp/mailer/license)](https://packagist.org/packages/initphp/mailer) [![PHP Version Require](http://poser.pugx.org/initphp/mailer/require/php)](https://packagist.org/packages/initphp/mailer)

> **Upgrading from 1.x?** Version 2.0 keeps the same fluent API but raises the
> PHP requirement, encapsulates state and replaces `bool` return values with
> exceptions. See [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Requirements

- PHP 8.1 or higher
- ext-mbstring, ext-iconv, ext-fileinfo

## Installation

```bash
composer require initphp/mailer
```

## Quick start

### SMTP

```php
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Exception\MailerException;

$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'your-password',
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

### Native `mail()`

```php
$mailer = Mailer::newInstance(); // protocol defaults to "mail"

$mailer->setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Hello')
    ->setMessage('Plain-text body')
    ->send();
```

### HTML with a plain-text alternative

```php
$mailer->setMailType('html')
    ->setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Newsletter')
    ->setMessage('<h1>Hello</h1><p>This is an <strong>HTML</strong> message.</p>')
    ->setAltMessage('Hello — this is the plain-text fallback.')
    ->send();
```

### Attachments and inline images

```php
$mailer->setMailType('html')
    ->setFrom('you@example.com')
    ->setTo('recipient@example.com')
    ->setSubject('Invoice')
    ->attach('/path/to/invoice.pdf');             // a file on disk

// In-memory / generated content (no temp file needed):
$pdf = $generator->render();
$mailer->attachContent($pdf, 'invoice.pdf', 'attachment', 'application/pdf');

// Inline image referenced from the HTML with cid:
$mailer->attach('/path/to/logo.png', 'inline');
$cid = $mailer->setAttachmentCID('/path/to/logo.png');
$mailer->setMessage('<img src="cid:' . $cid . '"> Welcome!')
    ->send();
```

## Error handling

`send()` returns `void` and throws on failure. Invalid input is rejected as soon
as it is supplied (fail-fast), not deferred to `send()`.

| Exception | When |
| --------- | ---- |
| `InvalidAddressException` | A sender/recipient address fails validation. |
| `ConfigurationException`  | A required value is missing (no sender, no recipient, empty SMTP host). |
| `AttachmentException`     | An attachment cannot be read or its type detected. |
| `TransportException`      | Delivery failed (the SMTP reply code is in `getCode()`). |

All extend `InitPHP\Mailer\Exception\MailerException`, so a single `catch` can
handle any failure.

## Facade

For quick, one-off usage there is a static facade backed by a shared instance:

```php
use InitPHP\Mailer\Facade\Mailer;

Mailer::setFrom('you@example.com')
    ->setTo('recipient@example.com')
    ->setSubject('Hi')
    ->setMessage('Body')
    ->send();
```

To configure the shared instance, build a `Mailer` and register it:

```php
use InitPHP\Mailer\Mailer as MailerInstance;
use InitPHP\Mailer\Facade\Mailer;

Mailer::setInstance(MailerInstance::newInstance(['protocol' => 'smtp', /* … */]));
```

## Documentation

Full developer documentation lives in [`docs/`](docs/README.md):

- [Getting started](docs/getting-started.md)
- [Configuration](docs/configuration.md)
- [Sending mail](docs/sending-mail.md)
- [Attachments](docs/attachments.md)
- [SMTP transport](docs/smtp-transport.md)
- [Encoding & headers](docs/encoding-and-headers.md)
- [Exceptions](docs/exceptions.md)
- [Facade](docs/facade.md)

## Contributing

Bug reports and pull requests are welcome on the
[issue tracker](https://github.com/InitPHP/Mailer/issues). New code should come
with tests; run the full check bundle before opening a PR:

```bash
composer ci   # php-cs-fixer (dry-run) + phpstan + phpunit
```

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Released under the [MIT License](./LICENSE). Copyright © 2022 InitPHP.
