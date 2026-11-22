# Attachments

There are two ways to attach data — a file on disk (`attach()`) or content you
already hold in memory (`attachContent()`) — plus a way to turn an attachment
into an inline image (`setAttachmentCID()`).

## A file from disk

```php
$mailer->attach('/path/to/invoice.pdf');
```

`attach()` reads the file, detects its MIME type with `ext-fileinfo`, and uses
the file's base name as the attachment name. Each of these can be overridden:

```php
$mailer->attach(
    '/path/to/invoice.pdf', // path
    'attachment',           // disposition: 'attachment' (default) or 'inline'
    'statement.pdf',        // name shown to the recipient
    'application/pdf',      // explicit MIME type (skip auto-detection)
);
```

If the file cannot be read, or its type cannot be detected and you didn't supply
one, `attach()` throws [`AttachmentException`](Error-Handling.md).

## In-memory or generated content

When the content isn't a file on disk — a generated PDF, an in-memory CSV, the
body of an API response — use `attachContent()` instead of writing a temp file:

```php
$pdf = $invoice->renderPdf(); // a string of bytes

$mailer->attachContent(
    $pdf,                 // raw content
    'invoice.pdf',        // name
    'attachment',         // disposition (default 'attachment')
    'application/pdf',    // MIME type (default 'application/octet-stream')
);
```

Have an open stream? Read it into a string and pass that:

```php
$mailer->attachContent(stream_get_contents($handle), 'export.bin');
```

> `attachContent()` replaces the never-working "pass a resource to `attach()`"
> behaviour from 1.x. See [Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md).

## Inline images (CID)

Inline images are attached and then referenced from the HTML body by their
Content-ID:

1. attach the image with disposition `inline`,
2. promote it with `setAttachmentCID()`, passing the same identifier you attached
   it with — this returns the generated CID,
3. use that CID in a `cid:` URL in the HTML.

```php
$mailer->setMailType('html')
    ->attach('/path/to/logo.png', 'inline');

$cid = $mailer->setAttachmentCID('/path/to/logo.png');

$mailer->setMessage('<p><img src="cid:' . $cid . '"> Welcome aboard!</p>');
```

For content added with `attachContent()`, look it up by its **name**:

```php
$mailer->attachContent($pngBytes, 'logo.png', 'inline', 'image/png');
$cid = $mailer->setAttachmentCID('logo.png');
```

`setAttachmentCID()` throws `AttachmentException` if no attachment matches the
identifier you pass.

## Multiple attachments and mixed types

You can add as many attachments as you like, freely mixing regular and inline
ones. The mailer builds the correct MIME nesting:

```php
$mailer->setMailType('html')
    ->setMessage('<p><img src="cid:' . $logoCid . '"></p><p>Invoice attached.</p>')
    ->attach('/path/to/logo.png', 'inline')      // related
    ->attach('/path/to/invoice.pdf');            // mixed

$logoCid = $mailer->setAttachmentCID('/path/to/logo.png');
```

| Attachments present | Body type |
| --- | --- |
| Regular only | `multipart/mixed` |
| Inline only | `multipart/related` |
| Both | `multipart/mixed` → `multipart/related` |

## Lifetime

Attachments persist across `send()` calls (unlike recipients and the body), so
the same attachment can go to several messages. Drop them with `clear(true)`.

```php
$mailer->attach('/path/to/report.pdf');
$mailer->setTo('a@example.com')->setSubject('Report')->setMessage('…')->send();
$mailer->setTo('b@example.com')->setSubject('Report')->setMessage('…')->send(); // same PDF
$mailer->clear(true); // now drop the attachment
```
