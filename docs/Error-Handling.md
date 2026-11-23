# Error Handling

The library never returns a status to check — it **throws**. Every exception
extends `InitPHP\Mailer\Exception\MailerException`, so one `catch` can handle any
failure, or you can catch a specific subtype.

```
MailerException                 (extends \RuntimeException)
├── ConfigurationException
├── InvalidAddressException
├── AttachmentException
└── TransportException
```

| Exception | Thrown when | Thrown by |
| --- | --- | --- |
| `InvalidAddressException` | An address fails validation (and validation is on). | `setFrom()`, `setReplyTo()`, `setTo()`, `setCC()`, `setBCC()` |
| `ConfigurationException` | A required value is missing: no sender, no recipient, empty SMTP host, auth without credentials. | `send()` |
| `AttachmentException` | A file can't be read, its type can't be detected, or `setAttachmentCID()` finds no match. | `attach()`, `setAttachmentCID()` |
| `TransportException` | Delivery failed: refused connection, unexpected SMTP reply, sendmail non-zero exit, or `mail()` returning false. | `send()` |

## Catch everything

```php
use InitPHP\Mailer\Exception\MailerException;

try {
    $mailer->setFrom('you@example.com')
        ->setTo('recipient@example.com')
        ->setSubject('Hello')
        ->setMessage('Body')
        ->send();
} catch (MailerException $e) {
    error_log('Mail failed: ' . $e->getMessage());
}
```

## Fail-fast on bad input

Invalid input is rejected where it is supplied, not deferred to `send()`. This
gives you a precise stack trace at the offending call:

```php
use InitPHP\Mailer\Exception\InvalidAddressException;

try {
    $mailer->setTo('not-an-address'); // throws here, immediately
} catch (InvalidAddressException $e) {
    // …
}
```

Need to accept addresses the PHP filter rejects? Turn validation off:

```php
$mailer = Mailer::newInstance(['validate' => false]);
```

> **Security:** `validate => false` removes the only check on addresses. The
> mailer only trims whitespace from the ends of an address, so a CR or LF inside
> one (`user@example.com\r\nBcc: attacker@example.test`) is written into the
> `From` / `Cc` header or the SMTP command as given, which lets a request-supplied
> address add headers or recipients. If you turn validation off, reject line
> breaks yourself before the address reaches the mailer:
>
> ```php
> if (preg_match('/[\r\n]/', $address) === 1) {
>     throw new \InvalidArgumentException('Invalid address.');
> }
> ```
>
> Never turn validation off for addresses that come from user input.

## Handle delivery failures specifically

`TransportException` carries the SMTP reply code (when there is one) in its code,
so you can branch on it:

```php
use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\TransportException;

try {
    $mailer->setFrom('you@example.com')
        ->setTo('user@example.com')
        ->setSubject('Hi')
        ->setMessage('…')
        ->send();
} catch (TransportException $e) {
    // e.g. 535 = auth failed, 550 = mailbox unavailable
    error_log(sprintf('Delivery failed (%d): %s', $e->getCode(), $e->getMessage()));
} catch (ConfigurationException $e) {
    error_log('Misconfigured: ' . $e->getMessage());
}
```

## Attachment errors

```php
use InitPHP\Mailer\Exception\AttachmentException;

try {
    $mailer->attach('/path/that/may/not/exist.pdf');
} catch (AttachmentException $e) {
    // file unreadable, or MIME type undetectable with no explicit type
}
```

## Why exceptions (and not `bool`)?

In 1.x `send()` returned `bool` and accumulated messages you had to read with
`printDebugger()`. That made it easy to ignore failures and impossible to tell
*why* something failed. 2.0 throws typed exceptions instead, with the SMTP reply
code attached where relevant. `printDebugger()` still exists, but only as a way
to inspect the generated message — see
[Encoding & Headers](Encoding-and-Headers.md#inspecting-the-built-message).
