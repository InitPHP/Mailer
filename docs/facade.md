# Facade

`InitPHP\Mailer\Facade\Mailer` is a thin static facade over a single shared
`Mailer` instance. It lets you write `Mailer::setTo(...)->...` without holding a
reference — handy for quick scripts and small apps.

```php
use InitPHP\Mailer\Facade\Mailer;

Mailer::setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Hello')
    ->setMessage('Body')
    ->send();
```

The first call lazily creates a default (`mail` protocol) instance. Every static
call is forwarded to it; because the underlying `set*` methods return the
instance, the chain reads exactly like the object API.

## Configuring the shared instance

To use SMTP or any non-default settings, build a configured `Mailer` and
register it once (at bootstrap):

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
```

Pass `null` to reset it (the next call recreates the default instance):

```php
Mailer::setInstance(null);
```

## Caveats

The facade shares one instance process-wide, which carries state (the current
recipients, subject, body) between calls. Since `send()` clears that state on
success, sequential sends are fine; just be aware the shared instance is not
suited to concurrent composition of several different messages at once. When in
doubt, use a plain `Mailer` instance per message instead.
