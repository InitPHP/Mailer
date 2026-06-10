# Upgrading from 1.x to 2.0

Version 2.0 keeps the familiar fluent API (`setFrom()`, `setTo()`,
`setSubject()`, `setMessage()`, `send()`, …) but modernises the internals and
the error model. This guide lists every breaking change and how to adapt.

## 1. PHP 8.1 is now required

The minimum supported version was raised from 7.4 to **8.1**.

## 2. The Facade is now autoloaded via PSR-4

In 1.x the package autoloaded a single file (`src/Mailer.php`), so
`InitPHP\Mailer\Facade\Mailer` could not be loaded at all. 2.0 uses PSR-4 for
the whole `InitPHP\Mailer\` namespace. If you referenced the classes directly
nothing changes; if you relied on the `files` autoloader you can remove any
workaround you had.

## 3. Configuration is actually applied

In 1.x, `new Mailer($config)` / `Mailer::newInstance($config)` silently ignored
`$config`. If you worked around this by setting public properties by hand, you
can now pass the configuration array as originally intended:

```php
$mailer = Mailer::newInstance([
    'protocol' => 'smtp',
    'SMTPHost' => 'smtp.example.com',
    'SMTPUser' => 'user@example.com',
    'SMTPPass' => 'secret',
    'SMTPPort' => 587,
    'SMTPCrypto' => 'tls',
]);
```

## 4. Errors are thrown, not returned

`send()` used to return `bool` and collect messages you had to read with
`printDebugger()`. It now returns `void` and throws:

- `InvalidAddressException` — a recipient/sender address is invalid.
- `ConfigurationException` — required configuration is missing (e.g. no SMTP host).
- `AttachmentException` — an attachment could not be read or its type detected.
- `TransportException` — the transport failed (the SMTP reply code is exposed via `getCode()`).

All extend `InitPHP\Mailer\Exception\MailerException`, so you can catch the base
type. Invalid input is now rejected at the setter (fail-fast) instead of at
`send()`.

```php
use InitPHP\Mailer\Exception\MailerException;

try {
    $mailer->setTo('user@example.com')
        ->setSubject('Hello')
        ->setMessage('Body')
        ->send();
} catch (MailerException $e) {
    // log $e->getMessage(); $e->getCode() holds the SMTP reply code for TransportException
}
```

`printDebugger()` still exists, but only as a way to inspect the generated
headers/subject/body — it no longer reports errors.

## 5. State is encapsulated

The configuration and runtime state that used to be `public` properties are now
encapsulated and set through the constructor config array or the `set*()`
methods. Reading or writing those properties directly is no longer supported;
use the documented methods instead.

## 6. Attachments

`attach()` no longer pretends to accept a stream resource (it never worked under
strict types). Use:

- `attach(string $path, …)` — attach a file from disk.
- `attachContent(string $content, string $name, …)` — attach in-memory or
  generated content. For a stream, pass `stream_get_contents($handle)`.
