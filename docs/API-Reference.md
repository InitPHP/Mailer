# API Reference

The full public surface of `InitPHP\Mailer\Mailer`, plus the facade, exceptions
and transport types. Signatures are taken straight from the source.

All `set*`/`attach*`/`clear` methods return the `Mailer` (`self`) so they chain.

## Constructing

### `__construct(?array $config = null)`
Builds a mailer from an optional [configuration](Configuration.md) array.

### `static newInstance(?array $config = null): static`
Convenience factory; identical to `new Mailer($config)` but reads well in a
chain.

```php
$mailer = Mailer::newInstance(['protocol' => 'smtp', /* … */]);
```

## Addressing

### `setFrom(string $from, string $name = '', ?string $returnPath = null): self`
Sets the sender. `$from` may be `address` or `"Name" <address>`; `$name` wins
over an embedded name. `$returnPath` sets the envelope sender.
**Throws** `InvalidAddressException` — also when the address, the name or
`$returnPath` contains a CR, LF or NUL, whatever the `validate` option says.

### `setReplyTo(string $replyTo, string $name = ''): self`
Sets the `Reply-To`. Defaults to the sender when never called.
**Throws** `InvalidAddressException` (also for a CR, LF or NUL, even with
`validate` off).

### `setTo(string|array $to): self`
Sets the To recipients. Accepts one address, a comma/space-separated string, or
an array. **Throws** `InvalidAddressException`. An address containing a CR, LF or
NUL is rejected even with `validate` off; in a string, whitespace (including line
breaks) still separates addresses.

### `setCC(string|array $cc): self`
Sets the CC recipients. **Throws** `InvalidAddressException` (same line-break
rule as `setTo()`).

### `setBCC(string|array $bcc, ?int $limit = null): self`
Sets the BCC recipients. A non-null `$limit` enables [BCC batching](Sending-Mail.md#bcc-batching)
with that batch size. **Throws** `InvalidAddressException` (same line-break rule as
`setTo()`).

## Content

### `setSubject(string $subject): self`
Sets the subject. Encoded (RFC 2047 Q) automatically when it contains non-ASCII.
CR, LF and NUL are removed when the message is built.

### `setMessage(string $body): self`
Sets the body (plain text or HTML depending on the mail type). Carriage returns
are stripped and surrounding blank lines trimmed.

### `setAltMessage(string $str): self`
Sets the plain-text alternative used for HTML messages. Carriage returns are
stripped, like in `setMessage()`.

### `setMailType(string $type = 'text'): self`
`'html'` for the value `html` (case-insensitive), otherwise `'text'`.

## Attachments

### `attach(string $file, string $disposition = '', ?string $newName = null, ?string $mime = null): self`
Attaches a file from disk. `$disposition` defaults to `attachment` (use `inline`
for embedded images), `$newName` overrides the displayed name, `$mime` skips
auto-detection. **Throws** `AttachmentException`, also when the name, disposition
or MIME type contains a CR, LF or NUL.

### `attachContent(string $content, string $name, string $disposition = 'attachment', ?string $mime = null): self`
Attaches in-memory/generated content. `$mime` defaults to
`application/octet-stream`. **Throws** `AttachmentException` when the name,
disposition or MIME type contains a CR, LF or NUL.

### `setAttachmentCID(string $fileName): string`
Promotes a previously added attachment (matched by its path/name) to an inline
`related` part and returns its generated Content-ID.
**Throws** `AttachmentException` when no attachment matches.

## Formatting & headers

### `setHeader(string $header, string $value): self`
Adds a custom header. CR, LF and NUL are stripped from the value. A header the
library writes for the message itself (From, Subject, Cc with CC recipients, Bcc
with BCC recipients on sendmail and `mail()`, Date, Message-ID, …) wins over a
custom one of the same name, whatever the case or spacing; one it does not write
is kept as you set it. `To` is not special-cased and behaves as it always did
(see [Custom headers](Sending-Mail.md#custom-headers)). Use
`setTo()`/`setCC()`/`setBCC()` for recipients, not headers.
**Throws** `MailerException` when the header name is not printable ASCII
without `:`, a tab or an inner space.

### `setPriority(int $n = 3): self`
`X-Priority`, 1 (highest)–5 (lowest); out-of-range → 3.

### `setWordWrap(bool $wordWrap = true): self`
Enables/disables word-wrapping of plain-text bodies.

### `wordWrap(string $str, ?int $chars = null): string`
Word-wraps a string at `$chars` columns (or the configured width). Honours
`{unwrap}…{/unwrap}` markers.

### `setNewline(string $newLine = PHP_EOL): self`
Header line ending; one of `"\n"`, `"\r\n"`, `"\n\r"`, `"\r"` (else `PHP_EOL`).

### `setCRLF(string $crlf = PHP_EOL): self`
Body (quoted-printable) line ending; same accepted set.

### `getMessageID(): string`
Returns a unique `Message-ID` based on the sender domain.

## Sending

### `setProtocol(string $protocol = 'mail'): self`
`'mail'`, `'sendmail'` or `'smtp'` (else `'mail'`).

### `send(bool $autoClear = true): void`
Builds and sends the message. Clears per-message state afterwards unless
`$autoClear` is `false`.
**Throws** `ConfigurationException` (missing sender/recipient/SMTP host),
`InvalidAddressException` (invalid configured sender), `TransportException`
(delivery failure).

### `clear(bool $clearAttachments = false): self`
Resets per-message state. Pass `true` to also drop attachments.

## Inspection

### `getArchive(): ?array`
A credential-free snapshot of the last sent message, or `null` before the first
send.

### `printDebugger(array $include = ['headers', 'subject', 'body']): string`
HTML dump of the last built headers/subject/body. Restrict with the `$include`
list.

## Validation helpers

### `isValidEmail(string $mail): bool`
Whether one address is valid.

### `validateEmail(array $mails): bool`
Whether every address in the list is valid.

## Testing seams

### `setTransport(TransportInterface $transport): self`
Overrides the protocol-derived transport (used in [Testing](Testing.md)).

### `setBoundaryFactory(callable $factory): self`
Overrides the MIME boundary generator: `callable(string $prefix): string`.
`send()` throws a `MailerException` for an empty boundary or one containing a
quote, CR, LF or NUL.

---

## Facade — `InitPHP\Mailer\Facade\Mailer`

Static proxy to a shared `Mailer`. Every instance method above is callable
statically.

### `static setInstance(?MailerInstance $instance): void`
Sets (or resets, with `null`) the shared instance. See [Facade](Facade.md).

---

## Exceptions — `InitPHP\Mailer\Exception\*`

| Class | Extends |
| --- | --- |
| `MailerException` | `\RuntimeException` |
| `ConfigurationException` | `MailerException` |
| `InvalidAddressException` | `MailerException` |
| `AttachmentException` | `MailerException` |
| `TransportException` | `MailerException` (code = SMTP reply / sendmail exit) |

See [Error Handling](Error-Handling.md).

---

## Transports — `InitPHP\Mailer\Transport\*`

| Type | Role |
| --- | --- |
| `TransportInterface` | `send(PreparedEmail $email): void` |
| `MailTransport` | `mail()`; `__construct(EmailValidator $v, ?callable $mailFunction = null)` |
| `SendmailTransport` | sendmail pipe; `__construct(string $mailPath, EmailValidator $v, ?callable $pipe = null)` |
| `SmtpTransport` | SMTP over a `SocketClientInterface` |
| `Smtp\SocketClientInterface` | the socket abstraction |
| `Smtp\StreamSocketClient` | default `fsockopen()`-based socket |

See [Transports](Transports.md) and [Testing](Testing.md).
