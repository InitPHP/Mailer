# FAQ

### `mail()` works locally but not in production — why?

The `mail` transport only hands the message to whatever MTA the host's PHP is
configured to use. If that isn't set up (common on cloud hosts), nothing is
delivered even though `send()` succeeds. Use the [SMTP transport](SMTP-Transport.md)
with your provider's server instead.

### How do I send through Gmail / a typical provider?

```php
$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.gmail.com',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
    'SMTPUser'   => 'you@gmail.com',
    'SMTPPass'   => 'your-app-password',
]);
```

Use a provider-issued **app password**, not your account password, where the
provider requires one.

### `send()` doesn't return `true` anymore — how do I know it worked?

In 2.0 `send()` returns `void` and **throws** on failure. If it returns without
throwing, the message was handed off successfully. See
[Error Handling](Error-Handling.md) and
[Upgrading 1.x → 2.0](Upgrading-from-1.x-to-2.0.md).

### My configuration array seems ignored.

That was a 1.x bug — the constructor silently dropped the array. It is fixed in
2.0. Make sure you are on 2.0 and that you are passing the array to
`new Mailer($config)` / `Mailer::newInstance($config)`.

### How do I attach a generated file (e.g. a PDF) without writing it to disk?

Use [`attachContent()`](Attachments.md#in-memory-or-generated-content):

```php
$mailer->attachContent($pdfBytes, 'invoice.pdf', 'attachment', 'application/pdf');
```

### How do I embed an image in an HTML email?

Attach it as `inline`, get a CID, and reference it with `cid:` — see
[Attachments → inline images](Attachments.md#inline-images-cid).

### An address is rejected even though it looks valid.

Validation uses PHP's `FILTER_VALIDATE_EMAIL`. If you must accept an address it
rejects, disable validation:

```php
$mailer = Mailer::newInstance(['validate' => false]);
```

> **Security:** `validate => false` removes the format check on addresses, but
> not the line-break check. A CR, LF or NUL inside an address or a display name
> (`user@example.com\r\nBcc: attacker@example.test`) is rejected with an
> `InvalidAddressException` whether validation is on or off, so a request-supplied
> address cannot add headers or SMTP commands. Only a leading or trailing line
> break is trimmed. Still avoid turning validation off for addresses that come
> from user input: the filter is what keeps malformed addresses from reaching
> the server.

### Non-Latin subjects or names look like `=?UTF-8?Q?...?=`.

That's correct — it is RFC 2047 encoding. Mail clients decode it back to the
original text. See [Encoding & Headers](Encoding-and-Headers.md).

### My HTML email body arrives empty or truncated.

That was a 1.x quoted-printable bug that truncated HTML bodies; it is fixed in
2.0. Upgrade to 2.0.

### Should I use the facade or a `Mailer` instance?

The [facade](Facade.md) is convenient for quick, sequential sends. For testability
and when composing several messages, prefer a plain `Mailer` instance per
message.

### How do I send to a large BCC list efficiently?

Use [BCC batching](Sending-Mail.md#bcc-batching) (`setBCC($list, $size)`) so each
delivery carries only a slice, and consider
[SMTP keep-alive](SMTP-Transport.md#keep-alive) to reuse one connection.

### Can I test my mail code without a real server?

Yes — every side effect is injectable. See [Testing](Testing.md).

### Which PHP versions are supported?

PHP **8.2, 8.3, 8.4 and 8.5** are tested in CI, with `ext-mbstring`,
`ext-iconv` and `ext-fileinfo` enabled.
