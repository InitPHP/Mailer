# Configuration

A mailer is configured once, with an array passed to the constructor or
`newInstance()`. The keys keep the spelling they have had since 1.x
(`SMTPHost`, `mailType`, `CRLF`, …). Every key has a default, unknown keys are
ignored, and the values are normalised and validated as they are read.

```php
use InitPHP\Mailer\Mailer;

$mailer = Mailer::newInstance([
    'protocol'   => 'smtp',
    'SMTPHost'   => 'smtp.example.com',
    'SMTPUser'   => 'you@example.com',
    'SMTPPass'   => 'secret',
    'SMTPPort'   => 587,
    'SMTPCrypto' => 'tls',
    'charset'    => 'UTF-8',
    'priority'   => 1,
]);
```

> In 1.x the constructor silently ignored the array. In 2.0 the configuration is
> always applied — this is the headline fix of the release.

## Message defaults

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `fromEmail` | string | `''` | Sender used when [`setFrom()`](Sending-Mail.md) is not called. |
| `fromName` | string | `''` | Display name paired with `fromEmail`. |
| `mailType` | string | `'text'` | `'text'` or `'html'`. |
| `charset` | string | `'UTF-8'` | Character set; stored upper-cased. A CR, LF or NUL throws a `ConfigurationException`. |
| `priority` | int | `3` | `X-Priority`, 1 (highest)–5 (lowest); out-of-range → 3. |
| `altMessage` | string | `''` | Plain-text alternative for HTML messages. |
| `userAgent` | string | `'InitPHP Mailer'` | `User-Agent` / `X-Mailer` header value. A CR, LF or NUL throws a `ConfigurationException`. |
| `validate` | bool | `true` | Validate addresses as they are added. Turning it off removes the format check only; a CR, LF or NUL in an address is rejected either way — see [Error Handling](Error-Handling.md). |

## Transport selection

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `protocol` | string | `'mail'` | `'mail'`, `'sendmail'` or `'smtp'`; anything else → `'mail'`. |
| `mailPath` | string | `'/usr/sbin/sendmail'` | Path to the sendmail binary (sendmail only). |

See [Transports](Transports.md).

## SMTP

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `SMTPHost` | string | `''` | SMTP server host. |
| `SMTPPort` | int | `25` | SMTP server port. |
| `SMTPUser` | string | `''` | Username for `AUTH LOGIN`. |
| `SMTPPass` | string | `''` | Password for `AUTH LOGIN`. |
| `SMTPAuth` | bool | derived | Authenticate? Defaults to `true` when both user and pass are set. |
| `SMTPCrypto` | string | `''` | `''`, `'tls'` (STARTTLS) or `'ssl'` (implicit TLS). |
| `SMTPTimeout` | int | `5` | Connection/read timeout, in seconds. |
| `SMTPKeepAlive` | bool | `false` | Reuse one connection for several messages. |
| `DSN` | bool | `false` | Request Delivery Status Notifications. |

See [SMTP Transport](SMTP-Transport.md).

## Bodies and headers

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `wordWrap` | bool | `true` | Word-wrap plain-text bodies. |
| `wrapChars` | int | `76` | Column to wrap at. |
| `sendMultipart` | bool | `true` | Send `multipart/alternative` for HTML messages. |
| `newline` | string | `PHP_EOL` | Header line ending (`"\n"`, `"\r\n"`, `"\r"`). |
| `CRLF` | string | `"\n"` | Body line ending for the quoted-printable encoder. |

See [Encoding & Headers](Encoding-and-Headers.md).

## BCC batching

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `BCCBatchMode` | bool | `false` | Split a large BCC list into several messages. |
| `BCCBatchSize` | int | `200` | Maximum BCC recipients per message. |

See [Sending Mail → BCC batching](Sending-Mail.md#bcc-batching).

## Normalisation rules

The configuration object cleans up the values you pass, so a slightly-off value
never produces a broken message:

- `charset` is upper-cased (`utf-8` → `UTF-8`).
- `protocol` is lower-cased and must be one of `mail`/`sendmail`/`smtp`,
  otherwise it falls back to `mail`.
- `mailType` is `html` only for the exact (case-insensitive) value `html`,
  otherwise `text`.
- `priority` outside 1–5 falls back to `3`.
- `SMTPCrypto` other than `tls`/`ssl` becomes `''` (no encryption).
- `newline`/`CRLF` outside the four recognised endings fall back to the default.
- `SMTPAuth` defaults to `true` when both `SMTPUser` and `SMTPPass` are set.

## Settings that can change after construction

Most options are fixed once the mailer is built, but a few have setters so they
can vary per message:

| Setter | Equivalent config |
| --- | --- |
| `setProtocol()` | `protocol` |
| `setMailType()` | `mailType` |
| `setPriority()` | `priority` |
| `setWordWrap()` | `wordWrap` |
| `setAltMessage()` | `altMessage` |
| `setNewline()` | `newline` |
| `setCRLF()` | `CRLF` |
| `setBCC($list, $limit)` | `BCCBatchMode` + `BCCBatchSize` |
