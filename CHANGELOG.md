# Changelog

All notable changes to `initphp/mailer` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Security

- A CR, LF or NUL in an address, display name or return path is now rejected
  with an `InvalidAddressException` **whether or not** the `validate` option is
  on (`Address`, `setFrom()`, `setReplyTo()`, `setTo()`, `setCC()`, `setBCC()`).
  Before, `validate => false` also switched off the only line-break check, so a
  request-supplied address could add headers or SMTP commands. Leading and
  trailing line breaks are still trimmed, and a recipient string is still split
  on whitespace.
- `setHeader()` throws a `MailerException` for a header name containing a CR, LF
  or NUL. A header value keeps its documented behaviour: CR and LF are stripped,
  and NUL is now stripped too. The subject is stripped of NUL as well.
- Attachment names, dispositions and MIME types containing a CR, LF or NUL throw
  an `AttachmentException`; the `userAgent` and `charset` options containing one
  throw a `ConfigurationException`.
- `SmtpTransport` refuses a recipient or an SMTP command containing a CR, LF or
  NUL (`InvalidAddressException` / `TransportException`) instead of writing it to
  the socket.

### Fixed

- **SMTP connection state after a failure.** A failed greeting, `EHLO`, `STARTTLS`,
  TLS handshake, `AUTH`, recipient or message now closes the connection and
  forgets the "authenticated" flag. Before, the socket stayed open, so the next
  `send()` skipped `STARTTLS` and wrote `AUTH LOGIN` with the base64 credentials
  in clear text. A greeting other than `220` is now an error.
- `StreamSocketClient::enableCrypto()` refuses to start TLS while clear-text
  bytes are still in the read buffer (STARTTLS response injection).
- The `Message-ID` domain is taken from the parsed sender, not from the raw
  `fromEmail` option, and `Address::parse()` checks the whole input before the
  display name is split off, so a CR, LF or NUL cannot hide in the part that is
  discarded.
- A `<` or `>` inside an address is rejected (even with `validate` off): it could
  close the SMTP path and add parameters (`RCPT TO:<a@x.com> NOTIFY=NEVER>`).
- `setHeader()`: reserved headers win whatever the case of the name (a custom
  `from`, `BCC` or `Subject` is dropped instead of added as a second header), and
  a name with a space, a colon or any non-printable or non-ASCII character throws
  a `MailerException`.
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

[3.0.0]: https://github.com/InitPHP/Mailer/compare/2.0.0...3.0.0
[2.0.0]: https://github.com/InitPHP/Mailer/compare/1.1...2.0.0
