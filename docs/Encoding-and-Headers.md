# Encoding & Headers

The mailer handles RFC-compliant encoding for you — you rarely call an encoder
directly. This page explains what happens and which knobs you can turn.

## Character set

The charset is fixed once via the `charset` configuration key (default `UTF-8`)
and stored upper-cased. It drives subject/name encoding and the `charset=`
parameter on the content type.

```php
$mailer = Mailer::newInstance(['charset' => 'UTF-8']);
```

## Subjects and display names

Header values containing non-ASCII bytes are encoded with the RFC 2047 Q
("encoded-word") scheme. Pure-ASCII display names are quoted instead:

```php
$mailer->setFrom('you@example.com', 'John Doe');
//=> From: "John Doe" <you@example.com>

$mailer->setFrom('you@example.com', 'Münir Şafak');
//=> From: =?UTF-8?Q?M=C3=BCnir=20=C5=9Eafak?= <you@example.com>

$mailer->setSubject('Café ☕');
//=> Subject: =?UTF-8?Q?Caf=C3=A9=20=E2=98=95?=
```

Encoding happens when the message is built, so you always pass plain strings.
Line breaks and NUL are removed from a subject; a display name containing one is
rejected with an `InvalidAddressException`. With a `UTF-8` charset, bytes that are
not valid UTF-8 are replaced with `?` before encoding. With any other charset
the bytes are encoded as they are and must already be in that charset (the
mailer does not convert them).

## Body encoding

- **Plain text** uses the transfer encoding derived from the charset: `7bit` for
  `us-ascii` / `iso-2022-*`, otherwise `8bit`.
- **HTML** is encoded as **quoted-printable**. With `CRLF` set to `"\r\n"` the
  native `quoted_printable_encode()` is used; otherwise a built-in encoder runs
  so your chosen line ending is honoured.

## Word wrapping

Plain-text bodies are wrapped at `wrapChars` columns (default 76) when `wordWrap`
is on. Keep a span from being wrapped — a long URL, say — by surrounding it with
`{unwrap}…{/unwrap}` markers:

```php
$mailer->setMessage(
    "Open {unwrap}https://example.com/a/very/long/path/that/must/stay/intact{/unwrap} to continue."
);
```

The markers are removed from the final message. You can also wrap text yourself:

```php
echo $mailer->wordWrap('some long text', 40);
```

## Line endings

| Key | Applies to | Default |
| --- | --- | --- |
| `newline` | header lines and structural newlines | `PHP_EOL` |
| `CRLF` | quoted-printable body output | `"\n"` |

Both accept `"\n"`, `"\r\n"`, `"\n\r"` or `"\r"`; an unrecognised value falls
back to the default. RFC 822 specifies `"\r\n"`, but some servers only accept a
bare `"\n"` for quoted-printable, which is why `CRLF` defaults to `"\n"`.

```php
$mailer->setNewline("\r\n")->setCRLF("\r\n");
```

## Priority

```php
$mailer->setPriority(1); // 1 (Highest) … 5 (Lowest); default 3 (Normal)
//=> X-Priority: 1 (Highest)
```

Out-of-range values fall back to 3.

## Custom headers

Add any header with `setHeader()`. CR, LF and NUL are stripped from the value to
prevent header injection, and a header name that is not printable ASCII without
`:`, a tab or an inner space throws a `MailerException`. The headers the library
writes itself (From, Subject, Date, Message-ID, MIME-Version, X-Mailer, …, and
Cc/Bcc when there are such recipients) win over a custom header of the same name,
whatever the case or spacing; a header the library does not write is kept. `To`
is not special-cased and behaves as it always did. See
[Sending Mail](Sending-Mail.md#custom-headers).

```php
$mailer->setHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');
$mailer->setHeader('X-Mailer-Note', 'sent by the billing service');
```

## Headers the mailer sets for you

Every message automatically gets: `Date`, `From`, `Return-Path`, `Reply-To`,
`Cc` (if any), `User-Agent`, `X-Sender`, `X-Mailer`, `X-Priority`, `Message-ID`,
`MIME-Version`, plus the `Content-Type` / `Content-Transfer-Encoding` for the
body. `To`, `Subject` and `Bcc` are placed per transport (see
[Transports](Transports.md)).

## Inspecting the built message

`printDebugger()` returns an HTML dump of the last built headers, subject and
body — handy when debugging what actually goes on the wire:

```php
echo $mailer->printDebugger();                       // headers + subject + body
echo $mailer->printDebugger(['headers']);            // just the headers
echo $mailer->printDebugger(['headers', 'body']);    // headers + body
```
