# Encoding & headers

## Character set

The charset is set once via the `charset` configuration key (default `UTF-8`)
and is stored upper-cased. It controls subject/name encoding and the
`charset=` parameter of the content type.

## Subjects and display names

Header values that contain non-ASCII bytes are encoded with the RFC 2047 Q
("encoded-word") scheme. Pure-ASCII display names are quoted instead:

```php
$mailer->setFrom('you@example.com', 'John Doe');
//=> From: "John Doe" <you@example.com>

$mailer->setFrom('you@example.com', 'Münir Şafak');
//=> From: =?UTF-8?Q?M=C3=BCnir=20=C5=9Eafak?= <you@example.com>

$mailer->setSubject('Café ☕');
//=> Subject: =?UTF-8?Q?Caf=C3=A9=20=E2=98=95?=
```

You don't call the encoder yourself; it runs when the message is built.

## Body encoding

- **Plain text** is sent with the content-transfer-encoding derived from the
  charset — `7bit` for `us-ascii` / `iso-2022-*`, otherwise `8bit`.
- **HTML** is encoded as quoted-printable. When `CRLF` is `"\r\n"` the native
  `quoted_printable_encode()` is used; otherwise a built-in encoder honours the
  configured line ending.

## Word wrapping

Plain-text bodies are wrapped at `wrapChars` columns (default 76) when `wordWrap`
is enabled. Wrap text you want left intact between `{unwrap}` and `{/unwrap}`
markers:

```php
$mailer->setMessage("See {unwrap}https://example.com/a/very/long/link/that/should/not/wrap{/unwrap} for details.");
```

The markers are removed from the final message.

## Line endings

| Key | Applies to | Default |
| --- | ---------- | ------- |
| `newline` | Header lines and structural newlines | `PHP_EOL` |
| `CRLF` | Quoted-printable body output | `"\n"` |

Both accept `"\n"`, `"\r\n"`, `"\n\r"` or `"\r"`; an unrecognised value falls
back to the default. RFC 822 specifies `"\r\n"`, but some servers only accept a
bare `"\n"` for quoted-printable, which is why `CRLF` defaults to `"\n"`.

## Priority

```php
$mailer->setPriority(1); // 1 (Highest) … 5 (Lowest); default 3 (Normal)
//=> X-Priority: 1 (Highest)
```

## Custom headers

Add any header with `setHeader()`. CR and LF are stripped from the value to
prevent header injection, and headers the library manages (From, To, Subject,
Date, Message-ID, …) always take precedence.

```php
$mailer->setHeader('List-Unsubscribe', '<https://example.com/unsubscribe>');
$mailer->setHeader('X-Campaign-Id', 'summer-2026');
```

## Inspecting the built message

After (or instead of) sending, `printDebugger()` returns an HTML dump of the last
built headers, subject and body for debugging:

```php
echo $mailer->printDebugger();                       // headers + subject + body
echo $mailer->printDebugger(['headers']);            // just the headers
```
