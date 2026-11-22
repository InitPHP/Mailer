# Upgrading 1.x → 2.0

Version 2.0 keeps the familiar fluent API (`setFrom()`, `setTo()`,
`setSubject()`, `setMessage()`, `send()`, …) but modernises the internals, the
error model and the requirements. This page lists every breaking change and how
to adapt.

## 1. PHP 8.2 is required

The minimum version was raised from 7.4 to **8.1** in 2.0.0 and to **8.2** in the release after it. Run PHP **8.2** or newer.

## 2. The facade is autoloaded

In 1.x autoloading pointed at a single file, so
`InitPHP\Mailer\Facade\Mailer` could not be loaded at all. 2.0 uses PSR-4 for the
whole `InitPHP\Mailer\` namespace, so the [facade](Facade.md) works out of the box.

## 3. Configuration is actually applied

In 1.x the constructor guard was inverted and the configuration array was
silently ignored. In 2.0 it is always applied — pass it as originally intended:

```php
$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
]);
```

If you previously worked around this by setting public properties by hand, you
can delete that code. The configuration keys are unchanged
(`SMTPHost`, `mailType`, `CRLF`, …) — see [Configuration](Configuration.md).

## 4. Errors are thrown, not returned

`send()` used to return `bool` and collect messages you read with
`printDebugger()`. It now returns **`void`** and throws:

| Exception | When |
| --- | --- |
| `InvalidAddressException` | A sender/recipient address is invalid. |
| `ConfigurationException` | Missing sender, recipient or SMTP host. |
| `AttachmentException` | An attachment can't be read or typed. |
| `TransportException` | Delivery failed (SMTP reply code in `getCode()`). |

All extend `InitPHP\Mailer\Exception\MailerException`.

**Before (1.x):**

```php
if (!$mailer->send()) {
    echo $mailer->printDebugger();
}
```

**After (2.0):**

```php
use InitPHP\Mailer\Exception\MailerException;

try {
    $mailer->send();
} catch (MailerException $e) {
    error_log($e->getMessage());
}
```

Invalid input now throws at the setter (fail-fast), not at `send()`.
`printDebugger()` still exists, but only to inspect the generated message — it no
longer reports errors. See [Error Handling](Error-Handling.md).

## 5. State is encapsulated

The configuration/state that used to be `public` properties is now private. Read
and write it through the constructor config array and the `set*()` methods
instead of touching properties directly. (As a side effect, the post-send
`getArchive()` snapshot no longer leaks your SMTP password.)

## 6. Attachments: `attach()` vs `attachContent()`

`attach()` no longer pretends to accept a stream resource (it never worked under
strict types). Use:

- `attach(string $path, …)` — a file on disk.
- `attachContent(string $content, string $name, …)` — in-memory/generated
  content. For a stream, pass `stream_get_contents($handle)`.

```php
// 1.x (didn't actually work):
// $mailer->attach($resource, ...);

// 2.0:
$mailer->attachContent(stream_get_contents($resource), 'data.bin');
```

Also note `setAttachmentCID()` now returns the CID string and **throws**
`AttachmentException` when no attachment matches, instead of returning `false`.

## 7. Bugs fixed along the way

If you saw any of these in 1.x, they are gone in 2.0:

- configuration silently ignored;
- malformed `MAIL FROM` / `X-Sender` when a sender name was set;
- HTML quoted-printable bodies truncated to a single byte;
- malformed MIME header for HTML with only inline images;
- duplicated header block on the `mail()` transport;
- SMTP `QUIT` mishandled, leaking the socket;
- sender/reply-to display names always Q-encoded;
- uninitialised-property errors on some send paths.

See the project's `CHANGELOG.md` for the complete list.

## Quick checklist

- [ ] Running PHP 8.2+
- [ ] Wrapped `send()` (and the `set*` calls) in `try { … } catch (MailerException $e)`
- [ ] Removed any `if ($mailer->send())` checks
- [ ] Replaced direct public-property access with config/`set*()`
- [ ] Switched stream/`attach($resource)` usage to `attachContent()`
