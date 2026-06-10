# Sending mail

## Sender and reply-to

```php
$mailer->setFrom('you@example.com', 'Your Name');
$mailer->setReplyTo('support@example.com', 'Support');
```

If you never call `setReplyTo()`, the `Reply-To` header defaults to the sender.
You can also set the sender once in the configuration (`fromEmail` / `fromName`)
and skip `setFrom()` entirely.

`setFrom()` accepts an optional third argument, the envelope return path:

```php
$mailer->setFrom('you@example.com', 'Your Name', 'bounces@example.com');
```

## Recipients

`setTo()` accepts a single address, a comma/space-separated string, or an array.
`setCC()` and `setBCC()` behave the same way.

```php
$mailer->setTo('a@example.com');
$mailer->setTo('a@example.com, b@example.com');
$mailer->setTo(['a@example.com', 'b@example.com']);

$mailer->setCC('cc@example.com');
$mailer->setBCC(['x@example.com', 'y@example.com']);
```

Addresses here are bare addresses (no display name). With validation enabled
(the default) an invalid address throws `InvalidAddressException` immediately.

At least one of To, CC or BCC must be set, or `send()` throws
`ConfigurationException`.

## Subject and body

```php
$mailer->setSubject('Monthly report');
$mailer->setMessage('The plain-text body of the message.');
```

Non-ASCII subjects are RFC 2047 Q-encoded automatically — see
[Encoding & headers](encoding-and-headers.md).

## HTML messages

Switch to HTML with `setMailType('html')` (or the `mailType` config key). By
default the message is sent as `multipart/alternative` with a plain-text part
derived from the HTML; supply your own with `setAltMessage()`.

```php
$mailer->setMailType('html')
    ->setSubject('Newsletter')
    ->setMessage('<h1>Hello</h1><p>An <strong>HTML</strong> message.</p>')
    ->setAltMessage('Hello — the plain-text fallback.')
    ->setTo('recipient@example.com')
    ->send();
```

To send HTML only (no alternative part), set `sendMultipart` to `false` in the
configuration.

## BCC batching

When you BCC a large list, you can split it into separate messages so each
delivery carries only a slice of the recipients. Pass a batch size as the second
argument to `setBCC()` (or set `BCCBatchMode`/`BCCBatchSize` in the config):

```php
$mailer->setFrom('you@example.com')
    ->setTo('announce@example.com')
    ->setSubject('Announcement')
    ->setMessage('…')
    ->setBCC($thousandsOfAddresses, 200) // up to 200 BCC per message
    ->send();
```

The To and CC recipients ride along with the first batch only; the remaining
batches carry just their slice of the BCC list.

## Reusing the mailer

`send()` clears the per-message state afterwards (keeping attachments) so you can
send the next message. Pass `send(false)` to keep everything, and call
`clear(true)` to also drop attachments. After a successful send, `getArchive()`
returns a credential-free snapshot of what was sent.
