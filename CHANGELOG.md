# Changelog

All notable changes to `initphp/mailer` are documented here. The format is
based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/InitPHP/Mailer/compare/v1.1...HEAD
