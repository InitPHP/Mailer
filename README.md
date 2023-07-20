# Mailer

This is a simple library for sending mail with SMTP consisting of a single class.


[![Latest Stable Version](http://poser.pugx.org/initphp/mailer/v)](https://packagist.org/packages/initphp/mailer) [![Total Downloads](http://poser.pugx.org/initphp/mailer/downloads)](https://packagist.org/packages/initphp/mailer) [![Latest Unstable Version](http://poser.pugx.org/initphp/mailer/v/unstable)](https://packagist.org/packages/initphp/mailer) [![License](http://poser.pugx.org/initphp/mailer/license)](https://packagist.org/packages/initphp/mailer) [![PHP Version Require](http://poser.pugx.org/initphp/mailer/require/php)](https://packagist.org/packages/initphp/mailer)


## Instalation

```
composer require initphp/mailer
```

## Requirements

- PHP 7.4 or higher
- MBString Extension
- Iconv Extension
- FileInfo Extension

## Usage

```php
$config = [
    'mailType'  => 'text' // or 'html'
    'protocol'  => 'smtp' // or 'mail' or 'sendmail'
    'SMTPAuth'  => true,
    'SMTPHost'  => 'smtp.gmail.com',
    'SMTPUser'  => 'your-mail@gmail.com',
    'SMTPPass'  => 'YourMailPassword',
    'SMTPPort'  => 587
];
$mailer = \InitPHP\Mailer\Mailer::newInstance($config);

$mailer->setFrom('info@muhammetsafak.com.tr', 'Muhammet Şafak');
//$mailer->setTo('example@example.com');
//$mailer->setCC('john@hotmail.com');
//$mailer->setBCC('testing@gmail.com');
$mailer->setSubject('Mail Subject');
$mailer->setMessage('Mail Body Message');
$mailer->send();
```

## Methods

#### `newInstance()`

Creates a new mailer object and returns it.

```php
public static function newInstance(?array $config = null): \InitPHP\Mailer\Mailer
```

### `clear()`

```php
public function clear(bool $clearAttachments = false): self
```

### `setHeader()`

```php
public function setHeader(string $header, string $value): self
```

### `setFrom()`

```php
public function setFrom(string $from, string $name = '', ?string $returnPath = null): self
```

### `setReplyTo()`

```php
public function setReplyTo(string $replyTo, string $name = ''): self
```

### `setTo()`

```php
public function setTo(string|array $to): self
```

### `setCC()`

```php
public function setCC(string $cc): self
```

### `setBCC()`

```php
public function setBCC(string $bcc, ?int $limit = null): self
```

### `setSubject()`

```php
public function setSubject(string $subject): self
```

### `setMessage()`

```php
public function setMessage(string $body): self
```

### `setAttachmentCID()`

```php
public function setAttachmentCID(string $fileName): false|string
```

### `setAltMessage()`

```php
public function setAltMessage(string $str): self
```

### `setMailType()`

```php
public function setMailType(string $type = 'text'): self
```

- `$type` : `text` or `html`

### `setWordWrap()`

```php
public function setWordWrap(bool $wordWrap = true): self
```

### `setProtocol()`

```php
public function setProtocol(string $protocol = 'mail'): self
```

- `$protocol` : `mail`, `sendmail` or `smtp`

### `setPriority()`

```php
public function setPriority(int $n = 3): self
```

- `$n` : An integer between 1 and 5 inclusive.

### `setNewline()`

```php
public function setNewline(string $newLine = \PHP_EOL): self
```

### `setCRLF()`

```php
public function setCRLF(string $CRLF = \PHP_EOL): self
```

### `attach()`

```php
public function attach(string|resource $file, string $disposition = '', ?string $newName = null, ?string $mime = null): false|self
```

### `send()`

```php
public function send(bool $autoClear = true): bool
```

### `printDebugger()`

```php
public function printDebugger(array $include = ['headers', 'subject', 'body']): string
```

## Getting Help

If you have questions, concerns, bug reports, etc, please file an issue in this repository's Issue Tracker.

## Getting Involved

> All contributions to this project will be published under the MIT License. By submitting a pull request or filing a bug, issue, or feature request, you are agreeing to comply with this waiver of copyright interest.

There are two primary ways to help:

- Using the issue tracker, and
- Changing the code-base.
    
### Using the issue tracker

Use the issue tracker to suggest feature requests, report bugs, and ask questions. This is also a great way to connect with the developers of the project as well as others who are interested in this solution.

Use the issue tracker to find ways to contribute. Find a bug or a feature, mention in the issue that you will take on that effort, then follow the Changing the code-base guidance below.

### Changing the code-base

Generally speaking, you should fork this repository, make changes in your own fork, and then submit a pull request. All new code should have associated unit tests that validate implemented features and the presence or lack of defects. Additionally, the code should follow any stylistic and architectural guidelines prescribed by the project. In the absence of such guidelines, mimic the styles and patterns in the existing code-base.

## Credits

- [Muhammet ŞAFAK](https://www.muhammetsafak.com.tr) <<info@muhammetsafak.com.tr>>

## License

Copyright &copy; 2022 [MIT License](./LICENSE)
