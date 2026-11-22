# Installation

## Requirements

| Requirement | Notes |
| --- | --- |
| PHP **8.2** or newer | The library is fully typed and uses 8.2 language features. |
| `ext-mbstring` | Byte-accurate string handling for encoding and line wrapping. |
| `ext-iconv` | RFC 2047 header (Q) encoding. |
| `ext-fileinfo` | MIME-type detection for attachments. |

These extensions ship with most PHP builds. You can confirm they are enabled:

```bash
php -m | grep -E 'mbstring|iconv|fileinfo'
```

To actually deliver mail you also need **one** of:

- a working `mail()` setup (an MTA configured in `php.ini`),
- a local **sendmail** binary, or
- access to an **SMTP** server.

See [Transports](Transports.md) for how to choose.

## Install with Composer

```bash
composer require initphp/mailer
```

That's the whole installation — there are no runtime dependencies beyond the PHP
extensions above.

## Verify it works

```php
<?php

require 'vendor/autoload.php';

use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance();

var_dump($mailer->isValidEmail('you@example.com')); // bool(true)
```

If that prints `bool(true)`, autoloading and the install are fine. Continue to
the [Quick Start](Quick-Start.md).

## Autoloading & namespaces

Everything lives under the `InitPHP\Mailer\` namespace and is autoloaded via
PSR-4:

| Namespace | Contains |
| --- | --- |
| `InitPHP\Mailer` | `Mailer`, `MailerConfig` |
| `InitPHP\Mailer\Facade` | the static `Mailer` facade |
| `InitPHP\Mailer\Exception` | `MailerException` and its subtypes |
| `InitPHP\Mailer\Transport` | the transport interface and implementations |
| `InitPHP\Mailer\Message` | `Address`, `Attachment`, value objects |
| `InitPHP\Mailer\Encoding` | header and body encoders |
