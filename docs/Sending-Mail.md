# Sending Mail

This page covers addressing and sending a message: the sender, reply-to,
recipients, subject and body, BCC batching, and reusing the mailer.

## The sender

```php
$mailer->setFrom('you@example.com', 'Your Name');
```

`setFrom()` accepts either a bare address or a `"Name" <address>` string; an
explicit second argument wins over a name embedded in the first:

```php
$mailer->setFrom('Your Name <you@example.com>');     // name parsed out
$mailer->setFrom('you@example.com', 'Your Name');    // name given explicitly
```

A third argument sets the envelope return path (used for bounces):

```php
$mailer->setFrom('you@example.com', 'Your Name', 'bounces@example.com');
```

You can also set the sender once in the configuration and skip `setFrom()`:

```php
$mailer = Mailer::newInstance(['fromEmail' => 'you@example.com', 'fromName' => 'Your Name']);
```

If neither is provided, `send()` throws `ConfigurationException`.

## Reply-To

```php
$mailer->setReplyTo('support@example.com', 'Support');
```

If you never call `setReplyTo()`, the `Reply-To` header defaults to the sender
address — correctly, including the display name.

## Recipients: To, CC, BCC

All three accept a single address, a comma/space-separated string, or an array:

```php
$mailer->setTo('a@example.com');
$mailer->setTo('a@example.com, b@example.com');
$mailer->setTo(['a@example.com', 'b@example.com']);

$mailer->setCC('cc@example.com');
$mailer->setBCC(['x@example.com', 'y@example.com']);
```

These are bare addresses (no display name). At least one of To, CC or BCC must be
set, or `send()` throws `ConfigurationException`.

With validation enabled (the default), an invalid address throws
`InvalidAddressException` **immediately**, at the `set*` call — not later at
`send()`:

```php
use InitPHP\Mailer\Exception\InvalidAddressException;

try {
    $mailer->setTo('not-an-address');
} catch (InvalidAddressException $e) {
    // handled right here
}
```

Disable validation with `'validate' => false` only if you need to accept
addresses the PHP filter rejects, and only for addresses you control. A CR, LF,
NUL, `<` or `>` inside an address, or a CR, LF or NUL in a display name, is
rejected with an `InvalidAddressException` even then, so a request-supplied
address cannot start another header or add parameters to an SMTP command — see
[Error Handling](Error-Handling.md#fail-fast-on-bad-input).

That check does **not** stop a request-supplied *string* from naming several
recipients: `setTo()` splits a string on commas and whitespace, so
`"victim@example.com,other@example.com"` is two recipients (with validation off,
a comma inside an array item is not split either, and the `To` header then lists
both). Pass a single untrusted address as an array item and keep `validate` on:

```php
$mailer->setTo([$_POST['email']]); // one address; validation rejects "a@x.com,b@x.com"
```

## Subject and body

```php
$mailer->setSubject('Monthly report');
$mailer->setMessage('The plain-text body of the message.');
```

Non-ASCII subjects are RFC 2047 Q-encoded automatically; see
[Encoding & Headers](Encoding-and-Headers.md). For HTML bodies, see
[HTML Messages](HTML-Messages.md).

## BCC batching

When BCC-ing a large list you can split it into several messages, each carrying
only a slice of the recipients. Pass a batch size as the second argument to
`setBCC()` (or set `BCCBatchMode`/`BCCBatchSize` in the config):

```php
$mailer->setFrom('you@example.com')
    ->setTo('announce@example.com')
    ->setSubject('Announcement')
    ->setMessage('…')
    ->setBCC($thousandsOfAddresses, 200) // up to 200 BCC per message
    ->send();
```

The To and CC recipients ride along with the **first** batch only; subsequent
batches carry just their slice of the BCC list, so To/CC are not delivered
repeatedly. If any batch fails, the failure is thrown.

## Custom headers

Add any header with `setHeader()`. CR, LF and NUL are stripped from the value to
prevent header injection (a trailing newline in a value is harmless; it is just
removed). A header *name* must be printable ASCII without `:` or spaces, or
`setHeader()` throws a `MailerException`.

If the library writes a header itself for this message, its value wins over a
custom header of the same name, whatever the case of the name: a custom `from`
is dropped, not added next to the real `From`. That covers From, Reply-To,
Return-Path, Date, Message-ID, MIME-Version, User-Agent, X-Mailer, X-Sender,
X-Priority, Content-Type and Content-Transfer-Encoding; `Subject`; `To` when you
called `setTo()`; `Cc` when you called `setCC()`; and `Bcc` when you called
`setBCC()` (sendmail and `mail()` write it; SMTP never does). A header the
library does **not** write is passed through as you set it: `setHeader('Cc', …)`
with no `setCC()`, or `setHeader('To', 'undisclosed-recipients:;')` on a message
that only has BCC recipients, adds that header.

Use `setTo()`, `setCC()` and `setBCC()` for recipients, and know what a header
set by hand does on each transport. With `mail()` and `sendmail` the MTA reads
the recipients from the headers, so a `Cc` or `Bcc` header set by hand **does**
add a recipient (and a `Bcc` header set by hand on `sendmail` is removed from the
delivered copy like any other `Bcc`). With `smtp` only the `RCPT TO` list counts:
a hand-set `Cc` or `Bcc` header adds no recipient, and a hand-set `Bcc` stays
**visible** in the message every recipient receives:

```php
$mailer->setHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');
$mailer->setHeader('X-Campaign-Id', 'summer-2026');
```

## Reusing the mailer & the archive

By default `send()` clears the per-message state afterwards (recipients, subject,
body, custom headers), keeping attachments, so the same mailer can send the next
message. Pass `send(false)` to keep everything; call `clear(true)` to also drop
attachments.

After a successful send, `getArchive()` returns a **credential-free** snapshot of
what was sent — useful for logging:

```php
$mailer->setFrom('you@example.com')->setTo('to@example.com')->setSubject('Hi')->send();

$archive = $mailer->getArchive();
// ['fromEmail' => 'you@example.com', 'to' => ['to@example.com'], 'subject' => 'Hi', …]
```

The archive never contains SMTP credentials.
