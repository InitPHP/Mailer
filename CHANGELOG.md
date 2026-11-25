# Changelog

All notable changes to `initphp/mailer` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [3.1.0] - 2026-11-25

### Security

- **Affected versions: 2.0.0 and 3.0.0.** The code these fixes change is the same in
  both (3.0.0 only reformatted it). 1.0 and 1.1 are a different, single-file code
  base and were not assessed.
- A CR, LF or NUL in an address or return path is now rejected with an
  `InvalidAddressException` **whether or not** the `validate` option is on
  (`Address`, `setFrom()`, `setReplyTo()`, `setTo()`, `setCC()`, `setBCC()`). A
  display name is not rejected: as in 3.0.0, an ASCII name is quoted with its
  control characters backslash-escaped and a non-ASCII name is Q-encoded with the
  line breaks removed, so it cannot start another header.
  Before, `validate => false` also switched off the only line-break check, so a
  request-supplied address could add headers or SMTP commands. Leading and
  trailing line breaks are still trimmed, and a recipient string is still split
  on whitespace.
- `setHeader()` throws a `MailerException` for a header name containing a CR, LF
  or NUL. A header value keeps its documented behaviour: CR and LF are stripped,
  and NUL is now stripped too. The subject keeps its behaviour too: CR and LF are
  removed and a NUL is Q-encoded (`=00`), never written raw.
- Attachment names, dispositions and MIME types containing a CR, LF or NUL throw
  an `AttachmentException`; the `userAgent` and `charset` options containing one
  throw a `ConfigurationException`.
- `SmtpTransport` refuses a recipient containing a CR, LF, NUL, `<` or `>`
  (`InvalidAddressException`) or a client hostname containing a CR, LF or NUL
  (`TransportException`) before it connects, instead of writing it to the socket.

### Changed

Behaviours that differ from 3.0.0 and are not security fixes in themselves:

- `setHeader()` throws a `MailerException` for a header name that is empty, has a
  tab or other whitespace, an inner space, a colon, or a non-ASCII or control
  character. 3.0.0 wrote `X: y: value` or a name with a tab as given; a colon or
  empty name produced a broken header. Spaces around a name are still accepted.
- A custom header with the same name as one the library writes is no longer
  written as a second line: `from`, `date`, `message-id`, `user-agent`, `x-mailer`,
  `x-sender`, `x-priority`, `mime-version`, `reply-to`, `return-path`,
  `content-type`, `content-transfer-encoding` (any case), `cc` next to
  `setCC()`, `bcc` next to `setBCC()` on sendmail and `mail()`, and `subject` (any case,
  and also the exact `Subject` on `mail()`). Before, 3.0.0 wrote both. A custom `cc`/`bcc` that followed a real CC
  or BCC list used to add that address as a recipient on sendmail and `mail()`;
  it no longer does. `To` is not affected.
- A header the library overrides keeps the position it has in the library's own
  list instead of the custom header's position.
- `"a>b"@example.com`-style addresses (a `<` or `>` inside the address) are
  rejected; the SMTP server greeting must be `220`.

### Fixed

- **SMTP connection state after a failure.** A failed greeting, `EHLO`, `STARTTLS`,
  TLS handshake, `AUTH`, recipient or message now closes the connection and
  forgets the "authenticated" flag. Before, the socket stayed open, so the next
  `send()` skipped `STARTTLS` and wrote `AUTH LOGIN` with the base64 credentials
  in clear text. A greeting other than `220` is now an error.
- With `DSN` on, the `ORCPT` address is xtext-encoded (RFC 3461); before, a space
  in an address (possible with `validate` off) could append SMTP parameters.
- `StreamSocketClient::enableCrypto()` refuses to start TLS while clear-text
  bytes are still in the read buffer (STARTTLS response injection).
- The `Message-ID` domain is taken from the parsed sender, not from the raw
  `fromEmail` option, so a CR, LF or NUL in that option cannot reach the header.
- A `<` or `>` inside an address is rejected (even with `validate` off): it could
  close the SMTP path and add parameters (`RCPT TO:<a@x.com> NOTIFY=NEVER>`).
- `setHeader()`: a header the library writes itself for the message (`Cc` with CC
  recipients, `Bcc` with BCC recipients, `Subject`, `From`, …) wins over a custom
  one of the same name whatever the case or the spacing (a custom `from`, or `bcc`
  next to a real BCC list, is dropped instead of added as a second header). A
  header the library does not write is kept as before. `To` is not special-cased:
  a custom `To` behaves exactly as in 3.0.0 on `mail()`, sendmail and SMTP,
  including in BCC batches, so it adds no recipient that 3.0.0 did not, for every
  spelling of the name that is still accepted (letter case, and spaces around
  it). A name with a tab or other whitespace, an inner space, a colon, or any
  non-printable or non-ASCII character throws a `MailerException` (see Changed).
- `setAltMessage()` strips carriage returns like `setMessage()`. The SMTP
  dot-stuffing now also handles a bare `"\r"` or `"\n\r"` newline.
- A `"` or `\` in an attachment name is escaped inside the quoted `name`
  parameter; the generated Content-ID keeps only `[A-Za-z0-9._-]` of the name.
- `QEncoder`: input that is not valid UTF-8 is replaced with `?` instead of
  raising iconv notices and producing garbage, and the fallback encoder for a
  non-UTF-8 charset writes the missing `=` before each byte, so the encoded-words
  are valid again.
- `HeaderRenderer` (used by all three transports) and the `mail()` / sendmail
  transports refuse a header name that is not a field name, a value with a line
  break other than a fold (a line break followed by a space or tab), and
  recipients with a line break or angle bracket, so a `PreparedEmail` built by
  hand cannot inject headers. A MIME boundary from `setBoundaryFactory()` that is
  empty or contains a quote, CR, LF or NUL throws a `MailerException`.

### Documentation

- Sending Mail, Configuration, Error Handling and the FAQ state that `validate =>
  false` switches off the format check only (a CR, LF, NUL, `<` or `>` in an
  address is rejected either way), and that a request-supplied *string* can still
  name several recipients: pass a single untrusted address as an array item and
  keep `validate` on.
- Encoding and Headers, Sending Mail and Transports describe the custom-header
  rules: the accepted header names, that headers the library writes win over a
  custom one of the same name, that `To` is not special-cased, that a `Bcc` header
  added by hand with `setHeader()` is visible to the recipients on SMTP (on
  sendmail it is removed from the delivered copy, and the recipients set with
  `setBCC()` never appear in the headers), and how a subject or display name with a line break
  or NUL is made safe.
- SMTP Transport documents that a failed send closes the connection and forgets
  the "authenticated" state, the xtext-encoded `ORCPT` address, the `220`
  greeting check and the new error cases. Attachments and the API reference list
  the CR, LF and NUL checks and the `Content-ID` and quoted-name rules.

## [3.0.0] - 2026-11-22

### Changed (breaking)

- **Requires PHP `^8.2`.** Support for PHP 8.1 is dropped; CI now runs PHP 8.2, 8.3, 8.4 and 8.5.
- **Coding standard moved to PER-CS** through the shared `.php-cs-fixer.dist.php`; the whole code base was reformatted.

## [2.0.0] - 2026-06-10

A correctness- and quality-focused overhaul: real bug fixes, a decomposed
SOLID architecture, an exception-based error model, full unit tests, static
analysis, CI and documentation. See [UPGRADE-2.0.md](UPGRADE-2.0.md) for the
breaking changes and how to migrate.

### Requirements

- **Raised the minimum PHP version to 8.1** (was 7.4).

### Added

- Exception hierarchy under `InitPHP\Mailer\Exception` (`MailerException` base
  with `ConfigurationException`, `InvalidAddressException`,
  `AttachmentException`, `TransportException`).
- `attachContent()` for attaching in-memory/generated content (e.g. a rendered
  PDF) without writing a temporary file.
- Pluggable transports behind `Transport\TransportInterface`
  (`MailTransport`, `SendmailTransport`, `SmtpTransport`), each with injectable
  side effects so they can be unit-tested without a real mail server.
- Full PHPUnit test suite, PHPStan (level max), PHP-CS-Fixer and a GitHub
  Actions CI pipeline.
- A `docs/` directory with English developer documentation.

### Changed

- **The `Facade\Mailer` class is now autoloaded.** Autoloading moved from a
  `files` entry pointing at `src/Mailer.php` to PSR-4 (`InitPHP\Mailer\`),
  which is what the rest of the InitPHP ecosystem uses.
- `send()` now returns `void` and throws on failure instead of returning a
  `bool` and accumulating messages. Invalid input is rejected at the setter
  (fail-fast) rather than being deferred to `send()`.
- The previously `public` configuration/state properties are now encapsulated.

### Fixed

- **`newInstance($config)` silently ignored the supplied configuration.** The
  constructor guard was inverted (`if ($config === null)`), so the documented
  primary usage applied none of the SMTP settings and never recomputed
  `SMTPAuth`; `charset` was also left lower-cased, which broke header
  Q-encoding. Configuration is now always applied and normalised.
- **SMTP `MAIL FROM` and the `X-Sender` header were malformed when a sender
  name was set.** Addresses were extracted by trimming `<>` off the full
  `"Name" <email>` header string instead of the address itself. Addresses are
  now modelled explicitly, so the bare addr-spec is always used on the wire.
- **The SMTP `QUIT` response code was assigned to the wrong variable**
  (`$rest` instead of `$resp`), so every quit was treated as a failure and the
  socket was never closed cleanly.
- **Quoted-printable HTML bodies were truncated to a single byte.** When the
  configured line ending was `"\n"` (the default), the encoder trimmed the
  trailing newline with a *positive* length instead of a negative one, so it
  returned only the first character of the encoded body. Every HTML message
  body was effectively destroyed.
- **HTML messages with only inline (related/CID) attachments produced a
  malformed MIME header** — the raw boundary id was written instead of the
  `Content-Type: multipart/related; boundary="…"` header.
- **`attach()` advertised resource support that could never work** under
  `declare(strict_types=1)`. Disk files use `attach()`; in-memory content uses
  the new `attachContent()`.
- **Uninitialised typed properties** (`fromEmail`, `fromName`, `archive`) could
  throw "must not be accessed before initialization" on some send paths.
- **`Reply-To` was derived from the full `From` header** (including the display
  name) instead of the sender address.
- **Sender/reply-to display names were always Q-encoded** because
  `preg_match(...) === false` (a regex-error check) was used where `=== 0`
  (no match) was meant.
- **The `mail()` transport duplicated the entire header block** — the header
  string was appended to itself (`.=` instead of `=`) when trimming the
  trailing newline.
- **7-bit charsets never selected 7-bit encoding** because the (upper-cased)
  charset was compared against lower-case prefixes; the comparison is now
  case-insensitive.
- The archive returned after a send no longer contains SMTP credentials.

[3.1.0]: https://github.com/InitPHP/Mailer/compare/3.0.0...3.1.0
[3.0.0]: https://github.com/InitPHP/Mailer/compare/2.0.0...3.0.0
[2.0.0]: https://github.com/InitPHP/Mailer/compare/1.1...2.0.0
