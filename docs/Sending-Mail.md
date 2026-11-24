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
addresses the PHP filter rejects, and only for addresses you control. The
mailer then no longer rejects a CR/LF inside an address, so a request-supplied
address could inject headers or recipients; reject line breaks yourself — see
[Error Handling](Error-Handling.md#fail-fast-on-bad-input).

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

Add any header with `setHeader()`. CR/LF are stripped from the value to prevent
header injection, and headers the library manages (From, To, Subject, …) take
precedence:

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
