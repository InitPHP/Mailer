# Quick Start

Every example below builds a message with the fluent API and calls `send()`.
`send()` returns nothing and **throws** on failure — see
[Error Handling](Error-Handling.md).

## 1. Create a mailer

```php
use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance();        // defaults to the mail() transport
```

`Mailer::newInstance($config)` and `new Mailer($config)` are equivalent. The
`$config` array is optional; see [Configuration](Configuration.md).

## 2. A plain-text message via `mail()`

```php
use InitPHP\Mailer\Exception\MailerException;

try {
    $mailer->setFrom('you@example.com', 'Your Name')
        ->setTo('recipient@example.com')
        ->setSubject('Hello')
        ->setMessage('This is the body of the message.')
        ->send();
} catch (MailerException $e) {
    echo 'Could not send: ' . $e->getMessage();
}
```

## 3. The same message over SMTP

Only the configuration changes — the build/send code is identical:

```php
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

## 4. An HTML message with attachments

```php
$mailer->setMailType('html')
    ->setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Your invoice')
    ->setMessage('<h1>Thank you!</h1><p>Your invoice is attached.</p>')
    ->setAltMessage('Thank you! Your invoice is attached.')
    ->attach('/path/to/invoice.pdf')
    ->send();
```

## 5. Several recipients

```php
$mailer->setFrom('you@example.com')
    ->setTo(['alice@example.com', 'bob@example.com'])
    ->setCC('manager@example.com')
    ->setBCC('audit@example.com')
    ->setSubject('Team update')
    ->setMessage('…')
    ->send();
```

## 6. Reusing the mailer

After a successful `send()`, the per-message state (recipients, subject, body,
custom headers) is cleared automatically, so you can send the next message right
away. Attachments are kept unless you call `clear(true)`.

```php
$mailer->setTo('first@example.com')->setSubject('One')->setMessage('…')->send();
$mailer->setTo('second@example.com')->setSubject('Two')->setMessage('…')->send();
```

Pass `send(false)` to keep the state instead.

## Next steps

- [Configuration](Configuration.md) — every option you can pass.
- [Sending Mail](Sending-Mail.md) — recipients, reply-to, BCC batching.
- [HTML Messages](HTML-Messages.md) and [Attachments](Attachments.md).
- [Error Handling](Error-Handling.md) — the exceptions and how to catch them.
