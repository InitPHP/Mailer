# Getting started

## Install

```bash
composer require initphp/mailer
```

Requires PHP 8.1+ with the `mbstring`, `iconv` and `fileinfo` extensions.

## Create a mailer

A mailer is created with an optional configuration array. With no argument it
defaults to the native `mail()` transport and a UTF-8, plain-text message.

```php
use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance();          // or: new Mailer();
```

`newInstance()` and `new Mailer()` are equivalent; `newInstance()` simply reads
better in a fluent chain.

## Build and send a message

Every `set*` method returns the mailer, so calls chain. `send()` returns nothing
and throws on failure.

```php
use InitPHP\Mailer\Exception\MailerException;

try {
    $mailer->setFrom('you@example.com', 'Your Name')
        ->setTo('recipient@example.com')
        ->setSubject('Hello from InitPHP Mailer')
        ->setMessage('This is the body of the message.')
        ->send();
} catch (MailerException $e) {
    echo 'Could not send: ' . $e->getMessage();
}
```

## Sending more than one message

By default `send()` clears the per-message state (recipients, subject, body,
headers) afterwards, so the same mailer can be reused for the next message.
Attachments are **not** cleared unless you ask:

```php
$mailer->setTo('first@example.com')->setSubject('One')->setMessage('…')->send();
$mailer->setTo('second@example.com')->setSubject('Two')->setMessage('…')->send();

$mailer->clear(true); // also drop attachments
```

Pass `send(false)` to keep the state instead of clearing it.

## What to read next

- [Configuration](configuration.md) — all the options you can pass.
- [Sending mail](sending-mail.md) — CC/BCC, HTML bodies and more.
- [SMTP transport](smtp-transport.md) — sending through an SMTP server.
