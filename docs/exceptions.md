# Exceptions

The library never returns a status to check — it throws. Every exception extends
`InitPHP\Mailer\Exception\MailerException`, so one `catch` can handle them all,
or you can catch a specific subtype.

```
MailerException                 (RuntimeException)
├── ConfigurationException
├── InvalidAddressException
├── AttachmentException
└── TransportException
```

| Exception | Thrown when | Thrown by |
| --------- | ----------- | --------- |
| `InvalidAddressException` | An address fails validation (and validation is on). | `setFrom()`, `setReplyTo()`, `setTo()`, `setCC()`, `setBCC()` |
| `ConfigurationException` | A required value is missing: no sender, no recipient, empty SMTP host. | `send()` |
| `AttachmentException` | A file cannot be read, its type cannot be detected, or `setAttachmentCID()` finds no match. | `attach()`, `setAttachmentCID()` |
| `TransportException` | Delivery failed: refused connection, unexpected SMTP reply, sendmail non-zero exit, or `mail()` returning false. | `send()` |

## Fail-fast

Invalid input is rejected where it is supplied, not deferred to `send()`:

```php
use InitPHP\Mailer\Exception\InvalidAddressException;

try {
    $mailer->setTo('not-an-address'); // throws here
} catch (InvalidAddressException $e) {
    // …
}
```

Disable validation with `'validate' => false` if you must accept addresses the
filter rejects.

## Handling delivery failures

`TransportException` carries the SMTP reply code (when there is one) in its code:

```php
use InitPHP\Mailer\Exception\MailerException;
use InitPHP\Mailer\Exception\TransportException;

try {
    $mailer->setFrom('you@example.com')
        ->setTo('user@example.com')
        ->setSubject('Hi')
        ->setMessage('…')
        ->send();
} catch (TransportException $e) {
    error_log(sprintf('Delivery failed (%d): %s', $e->getCode(), $e->getMessage()));
} catch (MailerException $e) {
    // configuration / address / attachment problems
    error_log('Could not build message: ' . $e->getMessage());
}
```
