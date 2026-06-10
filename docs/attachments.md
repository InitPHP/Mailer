# Attachments

## A file from disk

```php
$mailer->attach('/path/to/invoice.pdf');
```

`attach()` reads the file, detects its MIME type with `ext-fileinfo`, and uses
the file's base name as the attachment name. Optional arguments let you override
each of these:

```php
$mailer->attach(
    '/path/to/invoice.pdf', // path
    'attachment',           // disposition: 'attachment' (default) or 'inline'
    'statement.pdf',        // name shown to the recipient
    'application/pdf',      // MIME type, if you don't want auto-detection
);
```

If the file cannot be read, or its type cannot be detected and you did not
supply one, `attach()` throws `AttachmentException`.

## In-memory or generated content

When the content is not a file on disk — a generated PDF, a CSV built in memory,
the result of an API call — use `attachContent()` instead of writing a temp file:

```php
$pdf = $invoice->renderPdf();           // a string of bytes

$mailer->attachContent(
    $pdf,                 // raw content
    'invoice.pdf',        // name
    'attachment',         // disposition (default 'attachment')
    'application/pdf',    // MIME type (default 'application/octet-stream')
);
```

Have an open stream? Read it and pass the string:

```php
$mailer->attachContent(stream_get_contents($handle), 'export.bin');
```

## Inline images (CID)

Inline images are attached and then referenced from the HTML body by their
Content-ID. Attach the image (disposition `inline`), promote it with
`setAttachmentCID()` using the same identifier you attached it with, then use the
returned CID in a `cid:` URL.

```php
$mailer->setMailType('html')
    ->attach('/path/to/logo.png', 'inline');

$cid = $mailer->setAttachmentCID('/path/to/logo.png');

$mailer->setMessage('<img src="cid:' . $cid . '"> Welcome aboard!');
```

For an attachment added with `attachContent()`, look it up by its name:

```php
$mailer->attachContent($pngBytes, 'logo.png', 'inline', 'image/png');
$cid = $mailer->setAttachmentCID('logo.png');
```

`setAttachmentCID()` throws `AttachmentException` if no attachment matches the
given identifier.

Inline images produce a `multipart/related` body; mixing inline images and
regular attachments nests `multipart/related` inside `multipart/mixed`
automatically.

## Lifetime

Attachments are kept across `send()` calls (unlike recipients and the body), so
the same attachment can go to several messages. Call `clear(true)` to drop them.
