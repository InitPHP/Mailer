# Facade

`InitPHP\Mailer\Facade\Mailer` is a thin static facade over a single shared
`Mailer` instance. It lets you write `Mailer::setTo(...)->...` without holding a
reference — convenient for quick scripts and small apps.

```php
use InitPHP\Mailer\Facade\Mailer;

Mailer::setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Hello')
    ->setMessage('Body')
    ->send();
```

The first call lazily creates a default (`mail` protocol) instance. Each static
call is forwarded to it; because the underlying `set*` methods return the
instance, the chain reads exactly like the object API.

## Configuring the shared instance

To use SMTP or any non-default settings, build a configured `Mailer` and register
it once (at bootstrap):

```php
use InitPHP\Mailer\Mailer as MailerInstance;
use InitPHP\Mailer\Facade\Mailer;

Mailer::setInstance(MailerInstance::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
]));

// later, anywhere:
Mailer::setFrom('you@example.com')->setTo('to@example.com')->setSubject('Hi')->setMessage('…')->send();
```

Reset it by passing `null` (the next call recreates the default):

```php
Mailer::setInstance(null);
```

This `setInstance(null)` is also handy in tests, to clear state between cases.

## When *not* to use the facade

The facade shares one instance process-wide, which carries the current
recipients/subject/body between calls. Since `send()` clears that state on
success, sequential sends are fine. But it is not suited to composing several
different messages at the same time, and the shared mutable state can make code
harder to test. When in doubt, create a plain `Mailer` per message:

```php
use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance($config);
$mailer->setFrom(/* … */)->setTo(/* … */)->send();
```

Everything you can do on the facade you can do on a `Mailer` instance — see the
[API Reference](API-Reference.md).
