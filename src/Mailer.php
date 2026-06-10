<?php

/**
 * Mailer.php
 *
 * This file is part of InitPHP Mailer.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    https://github.com/InitPHP/Mailer/blob/main/LICENSE  MIT
 * @version    2.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer;

use InitPHP\Mailer\Encoding\QEncoder;
use InitPHP\Mailer\Encoding\QuotedPrintableEncoder;
use InitPHP\Mailer\Encoding\WordWrapper;
use InitPHP\Mailer\Exception\AttachmentException;
use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Message\Address;
use InitPHP\Mailer\Message\Attachment;
use InitPHP\Mailer\Message\HeaderRenderer;
use InitPHP\Mailer\Message\PreparedEmail;
use InitPHP\Mailer\Mime\MimeBuilder;
use InitPHP\Mailer\Transport\MailTransport;
use InitPHP\Mailer\Transport\SendmailTransport;
use InitPHP\Mailer\Transport\Smtp\StreamSocketClient;
use InitPHP\Mailer\Transport\SmtpTransport;
use InitPHP\Mailer\Transport\TransportInterface;
use InitPHP\Mailer\Validation\EmailValidator;

use function abs;
use function addcslashes;
use function array_chunk;
use function date;
use function floor;
use function htmlspecialchars;
use function implode;
use function preg_match;
use function preg_split;
use function str_starts_with;
use function strstr;
use function strtolower;
use function trim;
use function uniqid;

use const PHP_EOL;
use const PREG_SPLIT_NO_EMPTY;

/**
 * Composes an e-mail message through a fluent API and sends it through the
 * configured transport (`mail`, `sendmail` or `smtp`).
 *
 * The class orchestrates a set of focused collaborators — validation, header
 * Q-encoding, quoted-printable encoding, word wrapping, MIME assembly and the
 * transport — rather than doing the work itself.
 *
 * @phpstan-consistent-constructor
 */
class Mailer
{
    private MailerConfig $config;

    private EmailValidator $validator;

    /** @var array<int, string> */
    private const PRIORITIES = [
        1 => '1 (Highest)',
        2 => '2 (High)',
        3 => '3 (Normal)',
        4 => '4 (Low)',
        5 => '5 (Lowest)',
    ];

    private const NEWLINES = ["\n", "\r\n", "\n\r", "\r"];

    private const PROTOCOLS = ['mail', 'sendmail', 'smtp'];

    /* ----- Mutable settings (initialised from config, changeable via setters) ----- */

    private string $protocol;

    private string $mailType;

    private int $priority;

    private bool $wordWrap;

    private string $newline;

    private string $crlf;

    private string $altMessage;

    private bool $bccBatchMode;

    private int $bccBatchSize;

    /* ----- Per-message state (reset by clear()) ----- */

    private string $subject = '';

    private string $body = '';

    private ?Address $from = null;

    private ?Address $replyTo = null;

    private ?string $returnPath = null;

    /** @var list<string> */
    private array $to = [];

    /** @var list<string> */
    private array $cc = [];

    /** @var list<string> */
    private array $bcc = [];

    /** @var array<string, string> */
    private array $customHeaders = [];

    /** @var list<Attachment> */
    private array $attachments = [];

    private string $date = '';

    private string $finalHeaders = '';

    private string $finalSubject = '';

    private string $finalBody = '';

    /** @var array<string, mixed>|null */
    private ?array $archive = null;

    /* ----- Test seams / overrides ----- */

    private ?TransportInterface $transport = null;

    private ?SmtpTransport $smtpTransport = null;

    /** @var (callable(string): string)|null */
    private $boundaryFactory;

    /**
     * @param array<string, mixed>|null $config
     */
    public function __construct(?array $config = null)
    {
        $this->config = new MailerConfig($config ?? []);
        $this->validator = new EmailValidator();

        $this->protocol     = $this->config->protocol;
        $this->mailType     = $this->config->mailType;
        $this->priority     = $this->config->priority;
        $this->wordWrap     = $this->config->wordWrap;
        $this->newline      = $this->config->newline;
        $this->crlf         = $this->config->crlf;
        $this->altMessage   = $this->config->altMessage;
        $this->bccBatchMode = $this->config->bccBatchMode;
        $this->bccBatchSize = $this->config->bccBatchSize;

        $this->clear(true);
    }

    /**
     * @param array<string, mixed>|null $config
     */
    public static function newInstance(?array $config = null): static
    {
        return new static($config);
    }

    /**
     * Resets the per-message state. Attachments are kept unless
     * `$clearAttachments` is true.
     */
    public function clear(bool $clearAttachments = false): self
    {
        $this->subject       = '';
        $this->body          = '';
        $this->from          = null;
        $this->replyTo       = null;
        $this->returnPath    = null;
        $this->to            = [];
        $this->cc            = [];
        $this->bcc           = [];
        $this->customHeaders = [];
        $this->finalHeaders  = '';
        $this->finalSubject  = '';
        $this->finalBody     = '';
        $this->date          = $this->formatDate();

        if ($clearAttachments) {
            $this->attachments = [];
        }

        return $this;
    }

    /**
     * Adds an arbitrary header (for example `List-Unsubscribe`). CR/LF are
     * stripped from the value to prevent header injection. Reserved headers
     * managed by the library (From, To, Subject, …) take precedence.
     */
    public function setHeader(string $header, string $value): self
    {
        $this->customHeaders[$header] = (string) preg_replace('/[\r\n]+/', '', $value);

        return $this;
    }

    /**
     * Sets the sender. `$from` may be a bare address or `"Name" <address>`; an
     * explicit `$name` wins over a name embedded in `$from`.
     *
     * @throws InvalidAddressException
     */
    public function setFrom(string $from, string $name = '', ?string $returnPath = null): self
    {
        $address = Address::parse($from, $name);
        $this->assertValid($address->getEmail());

        if ($returnPath !== null) {
            $returnPath = trim($returnPath, "<> \t\n\r\0\x0B");
            $this->assertValid($returnPath);
        }

        $this->from = $address;
        $this->returnPath = $returnPath ?? $address->getEmail();

        return $this;
    }

    /**
     * @throws InvalidAddressException
     */
    public function setReplyTo(string $replyTo, string $name = ''): self
    {
        $address = Address::parse($replyTo, $name);
        $this->assertValid($address->getEmail());
        $this->replyTo = $address;

        return $this;
    }

    /**
     * @param string|array<array-key, string> $to
     *
     * @throws InvalidAddressException
     */
    public function setTo(string|array $to): self
    {
        $this->to = $this->normalizeAddressList($to);
        $this->assertAllValid($this->to);

        return $this;
    }

    /**
     * @param string|array<array-key, string> $cc
     *
     * @throws InvalidAddressException
     */
    public function setCC(string|array $cc): self
    {
        $this->cc = $this->normalizeAddressList($cc);
        $this->assertAllValid($this->cc);

        return $this;
    }

    /**
     * @param string|array<array-key, string> $bcc
     * @param int|null                        $limit When given, enables BCC
     *                                               batch mode with this size.
     *
     * @throws InvalidAddressException
     */
    public function setBCC(string|array $bcc, ?int $limit = null): self
    {
        if ($limit !== null) {
            $this->bccBatchMode = true;
            $this->bccBatchSize = $limit;
        }
        $this->bcc = $this->normalizeAddressList($bcc);
        $this->assertAllValid($this->bcc);

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function setMessage(string $body): self
    {
        $this->body = trim((string) preg_replace('/\r/', '', $body), "\n");

        return $this;
    }

    /**
     * Attaches a file from disk.
     *
     * @throws AttachmentException When the file cannot be read or typed.
     */
    public function attach(string $file, string $disposition = '', ?string $newName = null, ?string $mime = null): self
    {
        $this->attachments[] = Attachment::fromPath($file, $disposition, $newName, $mime);

        return $this;
    }

    /**
     * Attaches in-memory or generated content (for example a rendered PDF). For
     * a stream, pass `stream_get_contents($handle)`.
     */
    public function attachContent(string $content, string $name, string $disposition = 'attachment', ?string $mime = null): self
    {
        $this->attachments[] = Attachment::fromContent($content, $name, $disposition, $mime);

        return $this;
    }

    /**
     * Promotes a previously attached file to an inline (related) part and
     * returns its generated Content-ID. `$fileName` is the path/name used when
     * the attachment was added.
     *
     * @throws AttachmentException When no attachment matches `$fileName`.
     */
    public function setAttachmentCID(string $fileName): string
    {
        foreach ($this->attachments as $attachment) {
            if ($attachment->matchesSource($fileName)) {
                return $attachment->makeInline();
            }
        }

        throw new AttachmentException(\sprintf('No attachment matching "%s" was found.', $fileName));
    }

    public function setAltMessage(string $str): self
    {
        $this->altMessage = trim($str);

        return $this;
    }

    public function setMailType(string $type = 'text'): self
    {
        $this->mailType = strtolower(trim($type)) === 'html' ? 'html' : 'text';

        return $this;
    }

    public function setWordWrap(bool $wordWrap = true): self
    {
        $this->wordWrap = $wordWrap;

        return $this;
    }

    public function setProtocol(string $protocol = 'mail'): self
    {
        $protocol = strtolower(trim($protocol));
        $this->protocol = \in_array($protocol, self::PROTOCOLS, true) ? $protocol : 'mail';

        return $this;
    }

    public function setPriority(int $n = 3): self
    {
        $this->priority = ($n >= 1 && $n <= 5) ? $n : 3;

        return $this;
    }

    public function setNewline(string $newLine = PHP_EOL): self
    {
        $this->newline = \in_array($newLine, self::NEWLINES, true) ? $newLine : PHP_EOL;

        return $this;
    }

    public function setCRLF(string $crlf = PHP_EOL): self
    {
        $this->crlf = \in_array($crlf, self::NEWLINES, true) ? $crlf : PHP_EOL;

        return $this;
    }

    /**
     * Overrides the transport. Mainly a testing seam; when set, it is used
     * regardless of the configured protocol.
     */
    public function setTransport(TransportInterface $transport): self
    {
        $this->transport = $transport;

        return $this;
    }

    /**
     * Overrides the MIME boundary generator. Mainly a testing seam so the
     * generated message can be asserted deterministically.
     *
     * @param callable(string): string $factory
     */
    public function setBoundaryFactory(callable $factory): self
    {
        $this->boundaryFactory = $factory;
        $this->smtpTransport = null;

        return $this;
    }

    /**
     * Builds a unique Message-ID based on the sender domain.
     */
    public function getMessageID(): string
    {
        $from = $this->returnPath ?? $this->from?->getEmail() ?? $this->config->fromEmail;
        $domain = strstr($from, '@');

        return '<' . uniqid('', true) . ($domain === false ? '@localhost' : $domain) . '>';
    }

    /**
     * Whether every address in the list is valid.
     *
     * @param array<array-key, string> $mails
     */
    public function validateEmail(array $mails): bool
    {
        foreach ($mails as $mail) {
            if (!$this->validator->isValid($mail)) {
                return false;
            }
        }

        return true;
    }

    public function isValidEmail(string $mail): bool
    {
        return $this->validator->isValid($mail);
    }

    /**
     * Word-wraps a plain-text string at the given column (or the configured
     * width).
     */
    public function wordWrap(string $str, ?int $chars = null): string
    {
        return $this->wordWrapper()->wrap($str, $chars);
    }

    /**
     * Builds and sends the message.
     *
     * @throws ConfigurationException When the sender or a recipient is missing.
     * @throws InvalidAddressException When a configured sender address is invalid.
     * @throws \InitPHP\Mailer\Exception\TransportException When delivery fails.
     */
    public function send(bool $autoClear = true): void
    {
        $from = $this->resolveFrom();
        $replyTo = $this->replyTo ?? $from;

        if ($this->to === [] && $this->cc === [] && $this->bcc === []) {
            throw new ConfigurationException('At least one recipient (To, Cc or Bcc) is required.');
        }

        $transport = $this->resolveTransport();
        $headers = $this->buildGeneralHeaders($from, $replyTo);
        $subject = $this->qEncoder()->encode($this->subject);
        $content = $this->mimeBuilder()->build(
            $this->mailType,
            $this->body,
            $this->altMessage,
            $this->attachments,
            $this->resolveEncoding(),
        );

        $batchSize = max(1, $this->bccBatchSize);
        if ($this->bccBatchMode && \count($this->bcc) > $batchSize) {
            foreach (array_chunk($this->bcc, $batchSize) as $index => $chunk) {
                $transport->send($this->prepare(
                    $from,
                    $index === 0 ? $this->to : [],
                    $index === 0 ? $this->cc : [],
                    $chunk,
                    $subject,
                    $headers,
                    $content,
                ));
            }
        } else {
            $transport->send($this->prepare($from, $this->to, $this->cc, $this->bcc, $subject, $headers, $content));
        }

        $this->setArchive($from, $replyTo);

        if ($autoClear) {
            $this->clear();
        }
    }

    /**
     * Returns an HTML dump of the last built headers, subject and body for
     * debugging. Pass which parts to include.
     *
     * @param list<string> $include One or more of `headers`, `subject`, `body`.
     */
    public function printDebugger(array $include = ['headers', 'subject', 'body']): string
    {
        $raw = '';
        if (\in_array('headers', $include, true)) {
            $raw .= htmlspecialchars($this->finalHeaders) . PHP_EOL;
        }
        if (\in_array('subject', $include, true)) {
            $raw .= htmlspecialchars($this->finalSubject) . PHP_EOL;
        }
        if (\in_array('body', $include, true)) {
            $raw .= htmlspecialchars($this->finalBody) . PHP_EOL;
        }

        return $raw === '' ? '' : '<pre>' . $raw . '</pre>';
    }

    /**
     * A credential-free snapshot of the last sent message, or null if nothing
     * has been sent yet.
     *
     * @return array<string, mixed>|null
     */
    public function getArchive(): ?array
    {
        return $this->archive;
    }

    /* ----------------------------------------------------------------------- */

    /**
     * @param list<string>          $to
     * @param list<string>          $cc
     * @param list<string>          $bcc
     * @param array<string, string> $headers
     * @param array{headers: string, body: string} $content
     */
    private function prepare(
        Address $from,
        array $to,
        array $cc,
        array $bcc,
        string $subject,
        array $headers,
        array $content,
    ): PreparedEmail {
        $email = new PreparedEmail(
            $from,
            $to,
            $cc,
            $bcc,
            $subject,
            $headers,
            $content['headers'],
            $content['body'],
            $this->newline,
        );
        $this->captureForDebug($email);

        return $email;
    }

    private function resolveFrom(): Address
    {
        if ($this->from !== null) {
            return $this->from;
        }
        if ($this->config->fromEmail !== '') {
            $from = Address::parse($this->config->fromEmail, $this->config->fromName);
            $this->assertValid($from->getEmail());

            return $from;
        }

        throw new ConfigurationException(
            'A sender address is required. Call setFrom() or set "fromEmail" in the configuration.',
        );
    }

    private function resolveTransport(): TransportInterface
    {
        if ($this->transport !== null) {
            return $this->transport;
        }
        if ($this->protocol === 'smtp') {
            return $this->smtpTransport ??= $this->createSmtpTransport();
        }
        if ($this->protocol === 'sendmail') {
            return new SendmailTransport($this->config->mailPath, $this->validator);
        }

        return new MailTransport($this->validator);
    }

    private function createSmtpTransport(): SmtpTransport
    {
        return new SmtpTransport(
            new StreamSocketClient(),
            $this->config->smtpHost,
            $this->config->smtpPort,
            $this->config->smtpTimeout,
            $this->config->smtpCrypto,
            $this->config->smtpAuth,
            $this->config->smtpUser,
            $this->config->smtpPass,
            $this->config->smtpKeepAlive,
            $this->config->dsn,
            $this->config->smtpAuth || $this->resolveEncoding() === '8bit',
            $this->hostname(),
            $this->newline,
        );
    }

    /**
     * @return array<string, string>
     */
    private function buildGeneralHeaders(Address $from, Address $replyTo): array
    {
        $headers = $this->customHeaders;
        $headers['Date'] = $this->date;
        $headers['From'] = $this->formatAddressHeader($from);
        $headers['Return-Path'] = '<' . ($this->returnPath ?? $from->getEmail()) . '>';
        $headers['Reply-To'] = $this->formatAddressHeader($replyTo);
        if ($this->cc !== []) {
            $headers['Cc'] = implode(', ', $this->cc);
        }
        $headers['User-Agent'] = $this->config->userAgent;
        $headers['X-Sender'] = $from->getEmail();
        $headers['X-Mailer'] = $this->config->userAgent;
        $headers['X-Priority'] = self::PRIORITIES[$this->priority];
        $headers['Message-ID'] = $this->getMessageID();
        $headers['MIME-Version'] = '1.0';

        return $headers;
    }

    /**
     * Formats an address as a header value, quoting or RFC 2047 Q-encoding the
     * display name as needed.
     */
    private function formatAddressHeader(Address $address): string
    {
        if (!$address->hasName()) {
            return '<' . $address->getEmail() . '>';
        }

        $name = $address->getName();
        // 0 == no high bytes -> safe to quote; otherwise Q-encode.
        if (preg_match('/[\x80-\xFF]/', $name) === 0) {
            $name = '"' . addcslashes($name, "\0..\37\177'\"\\") . '"';
        } else {
            $name = $this->qEncoder()->encode($name);
        }

        return $name . ' <' . $address->getEmail() . '>';
    }

    private function captureForDebug(PreparedEmail $email): void
    {
        $headers = $email->headers;
        $headers['To'] = implode(', ', $email->to);
        $headers['Subject'] = $email->subject;

        $this->finalHeaders = HeaderRenderer::render($headers, $this->newline) . $email->contentHeaders;
        $this->finalSubject = $email->subject;
        $this->finalBody = $email->body;
    }

    private function setArchive(Address $from, Address $replyTo): void
    {
        $this->archive = [
            'fromEmail'   => $from->getEmail(),
            'fromName'    => $from->getName(),
            'replyTo'     => $replyTo->getEmail(),
            'returnPath'  => $this->returnPath ?? $from->getEmail(),
            'to'          => $this->to,
            'cc'          => $this->cc,
            'bcc'         => $this->bcc,
            'subject'     => $this->subject,
            'body'        => $this->body,
            'protocol'    => $this->protocol,
            'mailType'    => $this->mailType,
            'attachments' => \count($this->attachments),
        ];
    }

    /**
     * Derives the content-transfer-encoding from the charset.
     */
    private function resolveEncoding(): string
    {
        foreach (['us-ascii', 'iso-2022-'] as $base) {
            if (str_starts_with(strtolower($this->config->charset), $base)) {
                return '7bit';
            }
        }

        return '8bit';
    }

    /**
     * @param string|array<array-key, string> $value
     *
     * @return list<string>
     */
    private function normalizeAddressList(string|array $value): array
    {
        if (\is_string($value)) {
            $value = preg_split('/[\s,]+/', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        $list = [];
        foreach ($value as $item) {
            $item = trim((string) $item, "<> \t\r\n\0\x0B");
            if ($item !== '') {
                $list[] = $item;
            }
        }

        return $list;
    }

    /**
     * @throws InvalidAddressException
     */
    private function assertValid(string $email): void
    {
        if ($this->config->validate) {
            $this->validator->assertValid($email);
        }
    }

    /**
     * @param iterable<string> $emails
     *
     * @throws InvalidAddressException
     */
    private function assertAllValid(iterable $emails): void
    {
        if ($this->config->validate) {
            $this->validator->assertAllValid($emails);
        }
    }

    private function qEncoder(): QEncoder
    {
        return new QEncoder($this->config->charset, $this->crlf);
    }

    private function quotedPrintable(): QuotedPrintableEncoder
    {
        return new QuotedPrintableEncoder($this->crlf);
    }

    private function wordWrapper(): WordWrapper
    {
        return new WordWrapper($this->config->wrapChars, $this->newline);
    }

    private function mimeBuilder(): MimeBuilder
    {
        return new MimeBuilder(
            $this->config->charset,
            $this->newline,
            $this->quotedPrintable(),
            $this->wordWrapper(),
            $this->wordWrap,
            $this->config->sendMultipart,
            $this->boundaryFactory,
        );
    }

    private function hostname(): string
    {
        if (isset($_SERVER['SERVER_NAME']) && \is_string($_SERVER['SERVER_NAME'])) {
            return $_SERVER['SERVER_NAME'];
        }
        if (isset($_SERVER['SERVER_ADDR']) && \is_string($_SERVER['SERVER_ADDR'])) {
            return '[' . $_SERVER['SERVER_ADDR'] . ']';
        }

        return '[127.0.0.1]';
    }

    private function formatDate(): string
    {
        $offset = (int) date('Z');
        $operator = $offset < 0 ? '-' : '+';
        $offset = abs($offset);
        $hhmm = (int) (floor($offset / 3600) * 100 + ($offset % 3600) / 60);

        return \sprintf('%s %s%04d', date('D, j M Y H:i:s'), $operator, $hhmm);
    }
}
