# Configuration

Configuration is an array passed once when the mailer is created. Keys keep the
spelling used since 1.x (`SMTPHost`, `mailType`, `CRLF`, …). Unknown keys are
ignored; every key has a default, so you only set what you need.

```php
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

## Message defaults

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `fromEmail` | string | `''` | Sender used when `setFrom()` is not called. |
| `fromName` | string | `''` | Display name paired with `fromEmail`. |
| `mailType` | string | `'text'` | `'text'` or `'html'`. |
| `charset` | string | `'UTF-8'` | Character set; stored upper-cased. |
| `priority` | int | `3` | `X-Priority`, 1 (highest) – 5 (lowest). Out-of-range falls back to 3. |
| `altMessage` | string | `''` | Plain-text alternative for HTML messages. |
| `userAgent` | string | `'InitPHP Mailer'` | `User-Agent` / `X-Mailer` header value. |
| `validate` | bool | `true` | Validate addresses as they are added. |

## Transport selection

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `protocol` | string | `'mail'` | `'mail'`, `'sendmail'` or `'smtp'`. Anything else falls back to `'mail'`. |
| `mailPath` | string | `'/usr/sbin/sendmail'` | Path to the sendmail binary (sendmail only). |

## SMTP

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `SMTPHost` | string | `''` | SMTP server host. |
| `SMTPPort` | int | `25` | SMTP server port. |
| `SMTPUser` | string | `''` | Username for `AUTH LOGIN`. |
| `SMTPPass` | string | `''` | Password for `AUTH LOGIN`. |
| `SMTPAuth` | bool | derived | Authenticate? Defaults to true when both user and pass are set. |
| `SMTPCrypto` | string | `''` | `''`, `'tls'` (STARTTLS) or `'ssl'` (implicit TLS). |
| `SMTPTimeout` | int | `5` | Connection/read timeout in seconds. |
| `SMTPKeepAlive` | bool | `false` | Reuse one connection for several messages. |
| `DSN` | bool | `false` | Request Delivery Status Notifications. |

See [SMTP transport](smtp-transport.md) for details.

## Bodies and headers

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `wordWrap` | bool | `true` | Word-wrap plain-text bodies. |
| `wrapChars` | int | `76` | Column to wrap at. |
| `sendMultipart` | bool | `true` | Send `multipart/alternative` for HTML messages. |
| `newline` | string | `PHP_EOL` | Header line ending (`"\n"`, `"\r\n"`, `"\r"`). |
| `CRLF` | string | `"\n"` | Body line ending used by the quoted-printable encoder. |

## BCC batching

| Key | Type | Default | Description |
| --- | ---- | ------- | ----------- |
| `BCCBatchMode` | bool | `false` | Split large BCC lists into separate messages. |
| `BCCBatchSize` | int | `200` | Maximum BCC recipients per batch. |

See [Sending mail → BCC batching](sending-mail.md#bcc-batching).

## Changing settings after construction

Some settings have setters so they can change per message:
`setProtocol()`, `setMailType()`, `setPriority()`, `setWordWrap()`,
`setAltMessage()`, `setNewline()`, `setCRLF()`, and `setBCC($list, $limit)`
(which turns on batch mode). The rest are fixed at construction.
