# HTML Messages

## Switching to HTML

Set the message type to `html`, either in the configuration or with
`setMailType()`:

```php
$mailer = Mailer::newInstance(['mailType' => 'html']);
// or
$mailer->setMailType('html');
```

Then pass HTML to `setMessage()`:

```php
$mailer->setMailType('html')
    ->setFrom('you@example.com', 'Your Name')
    ->setTo('recipient@example.com')
    ->setSubject('Newsletter')
    ->setMessage('<h1>Hello</h1><p>This is an <strong>HTML</strong> message.</p>')
    ->send();
```

## The plain-text alternative

By default an HTML message is sent as **`multipart/alternative`**: a plain-text
part plus the HTML part, so clients that can't render HTML still show readable
text. If you don't supply a plain-text version, one is derived from the HTML
automatically (tags stripped, whitespace collapsed).

Supply your own with `setAltMessage()` — recommended, since an auto-derived
alternative is rarely as good as one you write:

```php
$mailer->setMailType('html')
    ->setMessage('<h1>Hello</h1><p>Welcome aboard.</p>')
    ->setAltMessage("Hello\n\nWelcome aboard.")
    ->setFrom('you@example.com')
    ->setTo('recipient@example.com')
    ->setSubject('Welcome')
    ->send();
```

The HTML part is encoded as **quoted-printable**; the plain-text part uses the
charset-appropriate transfer encoding.

## HTML only (no alternative)

To send HTML without a plain-text part, disable multipart in the configuration:

```php
$mailer = Mailer::newInstance(['mailType' => 'html', 'sendMultipart' => false]);
```

The body is then a single `text/html` quoted-printable part. (Some mail clients
prefer a multipart message, so leaving `sendMultipart` on is usually best.)

## Inline images

HTML messages frequently embed images by Content-ID. Attach the image as
`inline`, promote it with `setAttachmentCID()`, and reference the returned CID in
the HTML:

```php
$mailer->setMailType('html')
    ->attach('/path/to/logo.png', 'inline');

$cid = $mailer->setAttachmentCID('/path/to/logo.png');

$mailer->setMessage('<img src="cid:' . $cid . '"> Welcome!');
```

Inline images produce a `multipart/related` body; combining them with regular
attachments nests `multipart/related` inside `multipart/mixed` automatically. See
[Attachments](Attachments.md) for the full story.

## MIME structure at a glance

| Message | Resulting structure |
| --- | --- |
| HTML, multipart (default) | `multipart/alternative` → `text/plain` + `text/html` |
| HTML, `sendMultipart` off | single `text/html` |
| HTML + regular attachment | `multipart/mixed` → (`multipart/alternative` …) + attachment |
| HTML + inline image | `multipart/related` → (`multipart/alternative` …) + image |
| HTML + inline image + attachment | `multipart/mixed` → `multipart/related` → … |

You never assemble these by hand — the mailer builds the correct nesting from the
attachments you add and the message type you choose.
