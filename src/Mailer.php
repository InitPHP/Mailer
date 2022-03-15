<?php
/**
 * Mailer.php
 *
 * This file is part of InitPHP.
 *
 * @author     Muhammet ŞAFAK <info@muhammetsafak.com.tr>
 * @copyright  Copyright © 2022 InitPHP
 * @license    http://initphp.github.io/license.txt  MIT
 * @version    1.0
 * @link       https://www.muhammetsafak.com.tr
 */

declare(strict_types=1);

namespace InitPHP\Mailer;

use const STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
use const STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
use const FILTER_VALIDATE_EMAIL;
use const PHP_EOL;

use function array_merge;
use function get_object_vars;
use function fgets;
use function is_int;
use function time;
use function usleep;
use function mb_substr;
use function fwrite;
use function mb_strlen;
use function strpos;
use function base64_encode;
use function fclose;
use function strtolower;
use function strtoupper;
use function stream_socket_enable_crypto;
use function stream_set_timeout;
use function is_resource;
use function fsockopen;
use function preg_replace;
use function trim;
use function pclose;
use function popen;
use function function_exists;
use function mail;
use function explode;
use function implode;
use function is_array;
use function filter_var;
use function preg_match;
use function ucfirst;
use function str_replace;
use function preg_replace_callback;
use function dechex;
use function sprintf;
use function in_array;
use function ord;
use function quoted_printable_encode;
use function basename;
use function uniqid;
use function rtrim;
use function reset;
use function str_repeat;
use function strip_tags;
use function date;
use function floor;
use function abs;
use function is_string;
use function bin2hex;
use function iconv_substr;
use function str_split;
use function iconv_strlen;
use function iconv_mime_encode;
use function preg_split;
use function htmlspecialchars;
use function count;
use function wordwrap;
use function preg_match_all;
use function strstr;
use function chunk_split;
use function stream_get_contents;
use function fopen;
use function mime_content_type;
use function addcslashes;
use function method_exists;
use function property_exists;
use function get_class_vars;
use function array_keys;

class Mailer
{
    /**
     * Properties from the last successful send.
     *
     * @var array|null
     */
    public ?array $archive;

    /**
     * Properties to be added to the next archive.
     *
     * @var array
     */
    protected array $tmpArchive = [];

    /**
     * @var string
     */
    public string $fromEmail;

    /**
     * @var string
     */
    public string $fromName;

    /**
     * Used as the User-Agent and X-Mailer headers' value.
     *
     * @var string
     */
    public string $userAgent = 'PHPBasicMailer';

    /**
     * Path to the Sendmail binary.
     *
     * @var string
     */
    public string $mailPath = '/usr/sbin/sendmail';

    /**
     * Which method to use for sending e-mails.
     *
     * @var string 'mail', 'sendmail' or 'smtp'
     */
    public string $protocol = 'mail';

    /**
     * STMP Server host
     *
     * @var string
     */
    public string $SMTPHost = '';

    /**
     * SMTP Username
     *
     * @var string
     */
    public string $SMTPUser = '';

    /**
     * SMTP Password
     *
     * @var string
     */
    public string $SMTPPass = '';

    /**
     * SMTP Server port
     *
     * @var int
     */
    public int $SMTPPort = 25;

    /**
     * SMTP connection timeout in seconds
     *
     * @var int
     */
    public int $SMTPTimeout = 5;

    /**
     * SMTP persistent connection
     *
     * @var bool
     */
    public bool $SMTPKeepAlive = false;

    /**
     * SMTP Encryption
     *
     * @var string Empty, 'tls' or 'ssl'
     */
    public string $SMTPCrypto = '';

    /**
     * Whether to apply word-wrapping to the message body.
     *
     * @var bool
     */
    public bool $wordWrap = true;

    /**
     * Number of characters to wrap at.
     *
     * @see Email::$wordWrap
     *
     * @var int
     */
    public int $wrapChars = 76;

    /**
     * Message format.
     *
     * @var string 'text' or 'html'
     */
    public string $mailType = 'text';

    /**
     * Character set (default: utf-8)
     *
     * @var string
     */
    public string $charset = 'utf-8';

    /**
     * Alternative message (for HTML messages only)
     *
     * @var string
     */
    public string $altMessage = '';

    /**
     * Whether to validate e-mail addresses.
     *
     * @var bool
     */
    public bool $validate = true;

    /**
     * X-Priority header value.
     *
     * @var int 1-5
     */
    public int $priority = 3;

    /**
     * Newline character sequence.
     * Use "\r\n" to comply with RFC 822.
     *
     * @see http://www.ietf.org/rfc/rfc822.txt
     *
     * @var string "\r\n" or "\n"
     */
    public string $newline = \PHP_EOL;

    /**
     * CRLF character sequence
     *
     * RFC 2045 specifies that for 'quoted-printable' encoding,
     * "\r\n" must be used. However, it appears that some servers
     * (even on the receiving end) don't handle it properly and
     * switching to "\n", while improper, is the only solution
     * that seems to work for all environments.
     *
     * @see http://www.ietf.org/rfc/rfc822.txt
     *
     * @var string
     */
    public string $CRLF = "\n";

    /**
     * Whether to use Delivery Status Notification.
     *
     * @var bool
     */
    public bool $DSN = false;

    /**
     * Whether to send multipart alternatives.
     * Yahoo! doesn't seem to like these.
     *
     * @var bool
     */
    public bool $sendMultipart = true;

    /**
     * Whether to send messages to BCC recipients in batches.
     *
     * @var bool
     */
    public bool $BCCBatchMode = false;

    /**
     * BCC Batch max number size.
     *
     * @see Email::$BCCBatchMode
     *
     * @var int|string
     */
    public $BCCBatchSize = 200;

    /**
     * Subject header
     *
     * @var string
     */
    protected string $subject = '';

    /**
     * Message body
     *
     * @var string
     */
    protected string $body = '';

    /**
     * Final message body to be sent.
     *
     * @var string
     */
    protected string $finalBody = '';

    /**
     * Final headers to send
     *
     * @var string
     */
    protected string $headerStr = '';

    /**
     * SMTP Connection socket placeholder
     *
     * @var resource|null
     */
    protected $SMTPConnect;

    /**
     * Mail encoding
     *
     * @var string '8bit' or '7bit'
     */
    protected string $encoding = '8bit';

    /**
     * Whether to perform SMTP authentication
     *
     * @var bool
     */
    protected bool $SMTPAuth = false;

    /**
     * Whether to send a Reply-To header
     *
     * @var bool
     */
    protected bool $replyToFlag = false;

    /**
     * Debug messages
     *
     * @see Email::printDebugger()
     *
     * @var array
     */
    protected array $debugMessage = [];

    /**
     * Recipients
     *
     * @var array|string
     */
    protected array $recipients = [];

    /**
     * CC Recipients
     *
     * @var array
     */
    protected array $CCArray = [];

    /**
     * BCC Recipients
     *
     * @var array
     */
    protected array $BCCArray = [];

    /**
     * Message headers
     *
     * @var array
     */
    protected array $headers = [];

    /**
     * Attachment data
     *
     * @var array
     */
    protected array $attachments = [];

    /**
     * Valid $protocol values
     *
     * @see Email::$protocol
     *
     * @var array
     */
    protected array $protocols = [
        'mail',
        'sendmail',
        'smtp',
    ];

    /**
     * Character sets valid for 7-bit encoding,
     * excluding language suffix.
     *
     * @var array
     */
    protected array $baseCharsets = [
        'us-ascii',
        'iso-2022-',
    ];

    /**
     * Bit depths
     *
     * Valid mail encodings
     *
     * @see Email::$encoding
     *
     * @var array
     */
    protected array $bitDepths = [
        '7bit',
        '8bit',
    ];

    /**
     * $priority translations
     *
     * Actual values to send with the X-Priority header
     *
     * @var array
     */
    protected array $priorities = [
        1 => '1 (Highest)',
        2 => '2 (High)',
        3 => '3 (Normal)',
        4 => '4 (Low)',
        5 => '5 (Lowest)',
    ];


    public function __construct(?array $config = null)
    {
        $this->clear();
        if($config === null){
            foreach (array_keys(get_class_vars(static::class)) as $key) {
                if(property_exists($this, $key) && isset($config[$key])){
                    $method = 'set' . ucfirst($key);
                    if(method_exists($this, $method)){
                        $this->{$method}($config[$key]);
                    }else{
                        $this->{$key} = $config[$key];
                    }
                }
            }
            $this->charset = strtoupper($this->charset);
            $this->SMTPAuth = (!empty($this->SMTPUser) && !empty($this->SMTPPass));
        }
    }

    public function __destruct()
    {
        if(is_resource($this->SMTPConnect)){
            $this->sendCommand('quit');
        }
    }

    public static function newInstance(?array $config = null): Mailer
    {
        return new self($config);
    }

    public function clear(bool $clearAttachments = false): self
    {
        $this->subject      = '';
        $this->body         = '';
        $this->finalBody    = '';
        $this->headerStr    = '';
        $this->replyToFlag  = false;
        $this->recipients   = [];
        $this->CCArray      = [];
        $this->BCCArray     = [];
        $this->headers      = [];
        $this->debugMessage = [];

        $this->setHeader('Date', $this->setDate());

        if ($clearAttachments !== FALSE) {
            $this->attachments = [];
        }

        return $this;
    }

    public function setHeader(string $header, string $value): self
    {
        $this->headers[$header] = str_replace(["\n", "\r"], '', $value);
        return $this;
    }

    public function setFrom(string $from, string $name = '', ?string $returnPath = null): self
    {
        $name = trim($name);
        $from = trim($from, "<> \t\n\r\0\x0B");
        $this->validateCheck($from);
        if($returnPath !== null){
            $this->validateCheck($returnPath);
        }

        $this->tmpArchive['fromEmail'] = $from;
        $this->tmpArchive['fromName'] = $name;

        if($name !== ''){
            if(preg_match('/[\200-\377]/', $name) === FALSE){
                $name = '"' . addcslashes($name, "\0..\37\177'\"\\") . '"';
            }else{
                $name = $this->prepQEncoding($name);
            }
        }

        $this->setHeader('From', $name . '<' . $from . '>');
        if($returnPath === null){
            $returnPath = $from;
        }
        $this->setHeader('Return-Path', '<' . $returnPath . '>');
        $this->tmpArchive['returnPath'] = $returnPath;

        return $this;
    }

    public function setReplyTo(string $replyTo, string $name = ''): self
    {
        $replyTo = trim($replyTo, "<> \t\n\r\0\x0B");
        $name = trim($name);
        $this->validateCheck($replyTo);

        if($name !== ''){
            $this->tmpArchive['replyName'] = $name;
            if(preg_match('/[\200-\377]/', $name) === FALSE){
                $name = '"' . addcslashes($name, "\0..\37\177'\"\\") . '"';
            }else{
                $name = $this->prepQEncoding($name);
            }
        }
        $this->setHeader('Reply-To', $name . ' <' . $replyTo . '>');
        $this->replyToFlag = true;
        $this->tmpArchive['replyTo'] = $replyTo;
        return $this;
    }

    /**
     * @param array|string $to
     * @return self
     */
    public function setTo($to): self
    {
        $to = $this->str2Arr($to);
        $to = $this->cleanEmail($to);
        $this->validateCheck($to);
        if($this->getProtocol() !== 'mail'){
            $this->setHeader('To', implode(', ', $to));
        }
        $this->recipients = $to;
        return $this;
    }

    public function setCC(string $cc): self
    {
        $cc = $this->cleanEmail($this->str2Arr($cc));
        $this->validateCheck($cc);
        $this->setHeader('Cc', implode(', ', $cc));
        if($this->getProtocol() === 'smtp'){
            $this->CCArray = $cc;
        }
        $this->tmpArchive['CCArray'] = $cc;
        return $this;
    }

    public function setBCC(string $bcc, ?int $limit = null): self
    {
        if($limit !== null){
            $this->BCCBatchMode = true;
            $this->BCCBatchSize = $limit;
        }
        $bcc = $this->cleanEmail($this->str2Arr($bcc));
        $this->validateCheck($bcc);

        if($this->getProtocol() === 'smtp' || ($this->BCCBatchMode === TRUE && count($bcc) > $this->BCCBatchSize)){
            $this->BCCArray = $bcc;
            return $this;
        }

        $this->setHeader('Bcc', implode(', ', $bcc));
        $this->tmpArchive['BCCArray'] = $bcc;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->tmpArchive['subject'] = $subject;
        $subject = $this->prepQEncoding($subject);
        $this->setHeader('Subject', $subject);
        return $this;
    }

    public function setMessage(string $body): self
    {
        $this->body = rtrim(str_replace("\r", '', $body));
        return $this;
    }

    /**
     * @param string $fileName
     * @return bool|string
     */
    public function setAttachmentCID(string $fileName)
    {
        foreach ($this->attachments as $key => $attachment) {
            if($attachment['name'][0] === $fileName){
                $this->attachments[$key]['multipart'] = 'related';
                $this->attachments[$key]['cid'] = uniqid(basename($attachment['name'][0]) . '@', true);
                return $this->attachments[$key]['cid'];
            }
        }
        return false;
    }

    public function setAltMessage(string $str): self
    {
        $this->altMessage = trim($str);
        return $this;
    }

    public function setMailType(string $type = 'text'): self
    {
        $type = trim(strtolower($type));
        $this->mailType = ($type === 'html') ? 'html' : 'text';
        return $this;
    }

    public function setWordWrap(bool $wordWrap = true): self
    {
        $this->wordWrap = $wordWrap;
        return $this;
    }

    public function setProtocol(string $protocol = 'mail'): self
    {
        $protocol = trim(strtolower($protocol));
        $this->protocol = in_array($protocol, $this->protocols, true) ? $protocol : 'mail';
        return $this;
    }

    public function setPriority(int $n = 3): self
    {
        $this->priority = ($n >= 1 && $n <= 5) ? $n : 3;
        return $this;
    }

    public function setNewline(string $newLine = PHP_EOL): self
    {
        $this->newline = in_array($newLine, ["\n", "\r\n", "\n\r", "\r"], true) ? $newLine : PHP_EOL;
        return $this;
    }

    public function setCRLF(string $CRLF = PHP_EOL): self
    {
        $this->CRLF = in_array($CRLF, ["\n", "\r\n", "\n\r", "\r"], true) ? $CRLF : PHP_EOL;;
        return $this;
    }


    /**
     * @param string|resource $file
     * @param string $disposition
     * @param string|null $newName
     * @param string|null $mime
     * @return bool|self
     */
    public function attach(string $file, string $disposition = '', ?string $newName = null, ?string $mime = null)
    {
        if(empty($mime)){
            $mime = @mime_content_type($file);
            if($mime === FALSE){
                $this->setErrorMsg('The actual type of the file could not be found. You can try specifying manually.');
                return false;
            }
        }

        if(is_resource($file)){
            $fileContent = &$file;
        }else{
            if(($fOpen = @fopen($file, 'rb')) === FALSE){
                $this->setErrorMsg('The file could not be read.');
                return false;
            }
            $fileContent = stream_get_contents($fOpen);
            fclose($fOpen);
        }

        $this->attachments[] = [
            'name'          => [$file, $newName],
            'disposition'   => (empty($disposition) ? 'attachment' : $disposition),
            'type'          => $mime,
            'content'       => chunk_split(base64_encode($fileContent)),
            'multipart'     => 'mixed',
        ];

        return $this;
    }

    public function getMessageID(): string
    {
        $from = str_replace(['>', '<'], '', $this->headers['Return-Path']);
        return '<' . uniqid('', true) . strstr($from, '@') . '>';
    }

    public function validateEmail(array $mails): bool
    {
        foreach ($mails as $mail) {
            if($this->isValidEmail($mail) === FALSE){
                $this->setErrorMsg('One or more email addresses could not be verified.');
                return false;
            }
        }
        return true;
    }

    public function isValidEmail(string $mail): bool
    {
        return filter_var($mail, FILTER_VALIDATE_EMAIL) !== FALSE;
    }

    /**
     * @param string|array $mail
     * @return string|array
     */
    public function cleanEmail($mail)
    {
        if(!is_array($mail)){
            return trim($mail, "<>");
        }
        $mails = [];
        foreach ($mail as $row) {
            $mails[] = trim($row, "<>");
        }
        return $mails;
    }

    public function wordWrap(string $str, ?int $chars = null): string
    {
        if($chars === null || $chars <= 0){
            $chars = empty($this->wrapChars) ? 76 : $this->wrapChars;
        }
        if(strpos($str, "\r") !== FALSE){
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        $str = preg_replace('| +\n|', "\n", $str);
        $unWrap = [];
        preg_match_all('|\{unwrap\}(.+?)\{/unwrap\}|s', $str, $matches);
        if(isset($matches[1])){
            $count = count($matches[0]);
            for($i = 0; $i < $count; $i++){
                $unWrap[] = $matches[1][$i];
                $str = str_replace($matches[0][$i], '{{unwrapped' . $i . '}}', $str);
            }
        }
        $str = wordwrap($str, $chars, "\n", false);
        $output = '';
        $lines = explode("\n", $str);

        foreach ($lines as $line) {
            if(mb_strlen($line, '8bit') <= $chars){
                $output .= $line . $this->newline;
                continue;
            }
            $temp = '';
            do {
                if(preg_match('!\[url.+\]|://|www\.!', $line)){
                    break;
                }
                $temp .= mb_substr($line, 0, $chars - 1, '8bit');
                $line = mb_substr($line, $chars - 1, null, '8bit');
            }while(mb_strlen($line, '8bit') > $chars);
            if($temp !== ''){
                $output .= $temp . $this->newline;
            }
            $output .= $line . $this->newline;
        }
        if(!empty($unWrap)){
            foreach ($unWrap as $key => $value) {
                $output = str_replace('{{unwrapped' . $key . '}}', $value, $output);
            }
        }
        return $output;
    }

    public function send(bool $autoClear = true): bool
    {
        if(!isset($this->headers['From']) && !empty($this->fromEmail)){
            $this->setFrom($this->fromEmail, $this->fromName);
        }
        if(!isset($this->headers['From'])){
            $this->setErrorMsg('The e-mail address to send to was not found.');
            return false;
        }
        if($this->replyToFlag === FALSE){
            $this->setReplyTo($this->headers['From']);
        }
        if(empty($this->recipients) && !isset($this->headers['To']) && empty($this->BCCArray) && !isset($this->headers['Bcc']) && !isset($this->headers['Cc'])){
            $this->setErrorMsg('A recipient must be specified to send an e-mail.');
            return false;
        }
        $this->buildHeaders();
        if($this->BCCBatchMode === TRUE && count($this->BCCArray) > $this->BCCBatchSize){
            $this->batchBCCSend();
            if($autoClear === TRUE){
                $this->clear();
            }
            return true;
        }
        $this->buildMessage();
        $res = $this->spoolEmail();
        if($res){
            $this->setArchiveValues();
            if($autoClear === TRUE){
                $this->clear();
            }
        }
        return $res;
    }

    public function batchBCCSend(): void
    {
        $float = $this->BCCBatchSize - 1;
        $set = '';
        $chunk = [];

        $count = count($this->BCCArray);
        for($i = 0; $i < $count; $i++){
            if(isset($this->BCCArray[$i])){
                $set .= ', ' . $this->BCCArray[$i];
            }
            if($i === $float){
                $chunk[] = mb_substr($set, 1, null, '8bit');
                $float += $this->BCCBatchSize;
                $set = '';
            }
            if($i === ($count - 1)){
                $chunk[] = mb_substr($set, 1, null, '8bit');
            }
        }
        $count = count($chunk);
        for ($i = 0; $i < $count; $i++) {
            unset($this->headers['Bcc']);
            $bcc = $this->cleanEmail($this->str2Arr($chunk[$i]));
            if($this->protocol !== 'smtp'){
                $this->setHeader('Bcc', implode(', ', $bcc));
            }else{
                $this->BCCArray = $bcc;
            }
            $this->buildMessage();
            $this->spoolEmail();
        }
        $this->setArchiveValues();
    }

    public function printDebugger(array $include = ['headers', 'subject', 'body']): string
    {
        $out = implode("<br />" . PHP_EOL, $this->debugMessage);
        $rawData = '';
        if(in_array('headers', $include, true)){
            $rawData .= htmlspecialchars($this->headerStr) . PHP_EOL;
        }
        if(in_array('subject', $include, true)){
            $rawData .= htmlspecialchars($this->subject) . PHP_EOL;
        }
        if(in_array('body', $include, true)){
            $rawData .= htmlspecialchars($this->finalBody) . PHP_EOL;
        }

        return $out . ($rawData === '' ? '' : '<pre>' . $rawData . '</pre>');
    }

    /**
     * @param array|string $mail
     * @return string[]
     */
    protected function str2Arr($mail): array
    {
        if(!is_array($mail)){
            $mail = trim($mail);
            return (strpos($mail, ',') !== FALSE) ? preg_split('/[\s,]/', $mail, -1, \PREG_SPLIT_NO_EMPTY) : [$mail];
        }
        return $mail;
    }

    protected function prepQEncoding(string $str): string
    {
        $str = str_replace(["\r", "\n"], '', $str);
        if($this->charset === 'UTF-8'){
            if(($output = @iconv_mime_encode('', $str, [
                    'scheme'            => 'Q',
                    'line-length'       => 76,
                    'input-charset'     => $this->charset,
                    'output-charset'    => $this->charset,
                    'line-break-chars'  => $this->CRLF,
                ])) !== FALSE){
                return mb_substr($output, 2, null, '8bit');
            }
            if(($chars = iconv_strlen($str, 'UTF-8')) === FALSE){
                $chars = mb_strlen($str, 'UTF-8');
            }
        }
        if(!isset($chars)){
            $chars = mb_strlen($str, '8bit');
        }
        $output = '=?' . $this->charset . '?Q?';

        $len = mb_strlen($output);
        for ($i = 0; $i < $chars; $i++) {
            $chr = ($this->charset === 'UTF-8') ? '=' . implode('=', str_split(strtoupper(bin2hex(iconv_substr($str, $i, 1, $this->charset))), 2)) : strtoupper(bin2hex($str[$i]));

            if($len + ($l = mb_strlen($chr, '8bit')) > 74){
                $output .= '?=' . $this->CRLF . ' =?' . $this->charset . '?Q?' . $chr;
                $len = 6 + mb_strlen($this->charset, '8bit') + $l;
            }else{
                $output .= $chr;
                $len += $l;
            }
        }
        return $output . '?=';
    }

    protected function getProtocol(): string
    {
        $this->protocol = strtolower($this->protocol);
        if(!in_array($this->protocol, $this->protocols, true)){
            $this->protocol = 'mail';
        }
        return $this->protocol;
    }

    protected function setErrorMsg(string $msg): void
    {
        $this->debugMessage[] = $msg;
    }

    /**
     * @param string|array $mail
     * @return bool
     */
    protected function validateCheck($mail): bool
    {
        if($this->validate){
            if(is_string($mail)){
                $mail = $this->str2Arr($mail);
            }
            return $this->validateEmail($mail);
        }
        return true;
    }

    protected function getEncoding(): string
    {
        if(!in_array($this->encoding, $this->bitDepths, true)){
            $this->encoding = '8bit';
        }
        foreach ($this->baseCharsets as $charset) {
            if(strpos($this->charset, $charset) === 0){
                $this->encoding = '7bit';
                break;
            }
        }
        return $this->encoding;
    }

    protected function getContentType(): string
    {
        if($this->mailType === 'html'){
            return empty($this->attachments) ? 'html' : 'html-attach';
        }
        if($this->mailType === 'text' && !empty($this->attachments)){
            return 'plain-attach';
        }
        return 'plain';
    }

    protected function setDate(): string
    {
        $timezone = date('Z');
        $operator = ($timezone[0] === '-') ? '-' : '+';
        $timezone = abs((float)$timezone);
        $timezone = floor($timezone / 3600) * 100 + ($timezone % 3600) / 60;
        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $operator, $timezone);
    }

    protected function getMimeMessage(): string
    {
        return 'This is a multi-part message in MIME format.' . $this->newline . 'Your email application may not support this format.';
    }

    protected function getAltMessage(): string
    {
        if(!empty($this->altMessage)){
            return ($this->wordWrap) ? $this->wordWrap($this->altMessage, 76) : $this->altMessage;
        }
        $body = preg_match('/\<body.*?\>(.*)\<\/body\>/si', $this->body, $match) ? $match[1] : $this->body;
        $body = str_replace("\t", '', preg_replace('#<!--(.*)--\>#', '', trim(strip_tags($body))));
        for($i = 20; $i >= 3; $i--){
            $body = str_replace(str_repeat("\n", $i), "\n\n", $body);
        }
        $body = preg_replace('| +|', ' ', $body);
        return ($this->wordWrap) ? $this->wordWrap($body, 76) : $body;
    }

    protected function buildHeaders(): void
    {
        $this->setHeader('User-Agent', $this->userAgent);
        $this->setHeader('X-Sender', $this->cleanEmail($this->headers['From']));
        $this->setHeader('X-Mailer', $this->userAgent);
        $this->setHeader('X-Priority', $this->priorities[$this->priority]);
        $this->setHeader('Message-ID', $this->getMessageID());
        $this->setHeader('Mime-Version', '1.0');
    }

    protected function writeHeaders(): void
    {
        if($this->protocol === 'mail' && isset($this->headers['Subject'])){
            $this->subject = $this->headers['Subject'];
            unset($this->headers['Subject']);
        }
        reset($this->headers);
        $this->headerStr = '';
        foreach ($this->headers as $key => $value) {
            $value = trim($value);
            if($value !== ''){
                $this->headerStr .= $key . ': ' . $value . $this->newline;
            }
        }
        if($this->getProtocol() === 'mail'){
            $this->headerStr .= rtrim($this->headerStr);
        }
    }

    protected function buildMessage(): void
    {
        if($this->wordWrap === TRUE && $this->mailType !== 'html'){
            $this->body = $this->wordWrap($this->body);
        }
        $this->writeHeaders();
        $hdr = ($this->getProtocol() === 'mail') ? $this->newline : '';
        $body = '';

        switch ($this->getContentType()) {
            case 'plain':
                $hdr .= 'Content-Type: text/plain; charset='
                    . $this->charset
                    . $this->newline
                    . 'Content-Transfer-Encoding: '
                    . $this->getEncoding();
                if($this->getProtocol() === 'mail'){
                    $this->headerStr .= $hdr;
                    $this->finalBody = $this->body;
                }else{
                    $this->finalBody = $hdr . $this->newline . $this->newline .$this->body;
                }
                return;
            case 'html':
                $boundary = uniqid('B_ALT_', true);
                if($this->sendMultipart === FALSE){
                    $hdr .= 'Content-Type: text/html; charset='
                        . $this->charset
                        . $this->newline
                        . 'Content-Transfer-Encoding: quoted-printable';
                }else{
                    $hdr .= 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
                    $body .= $this->getMimeMessage()
                        . $this->newline . $this->newline
                        . '--' . $boundary . $this->newline
                        . 'Content-Type: text/plain; charset='
                        . $this->charset
                        . $this->newline
                        . 'Content-Transfer-Encoding: '
                        . $this->getEncoding()
                        . $this->newline . $this->newline
                        . $this->getAltMessage()
                        . $this->newline . $this->newline
                        . '--' . $boundary . $this->newline
                        . 'Content-Type: text/html; charset='
                        . $this->charset . $this->newline
                        . 'Content-Transfer-Encoding: quoted-printable'
                        . $this->newline . $this->newline;
                }
                $this->finalBody = $body . $this->prepQuotedPrintable($this->body) . $this->newline . $this->newline;
                if($this->getProtocol() === 'mail'){
                    $this->headerStr .= $hdr;
                }else{
                    $this->finalBody = $hdr . $this->newline . $this->newline . $this->finalBody;
                }
                if($this->sendMultipart !== FALSE){
                    $this->finalBody .= '--' . $boundary . '--';
                }
                return;
            case 'plain-attach':
                $boundary = uniqid('B_ATC_', true);
                $hdr .= 'Content-Type: multipart/mixed; boundary="' . $boundary . '"';
                if($this->getProtocol() === 'mail'){
                    $this->headerStr .= $hdr;
                }
                $body .= $this->getMimeMessage()
                    . $this->newline . $this->newline
                    . '--' . $boundary . $this->newline
                    . 'Content-Type: text/plain; charset='
                    . $this->charset . $this->newline
                    . 'Content-Transfer-Encoding: '
                    . $this->getEncoding()
                    . $this->newline . $this->newline
                    . $this->body . $this->newline . $this->newline;

                $this->appendAttachments($body, $boundary);

                break;
            case 'html-attach':
                $altBoundary = uniqid('B_ALT_', true);
                $lastBoundary = null;
                if($this->attachmentsHaveMultipart('mixed')){
                    $atcBoundary = uniqid('B_ATC_', true);
                    $hdr .= 'Content-Type: multipart/mixed; boundary="' . $atcBoundary . '"';
                    $lastBoundary = $atcBoundary;
                }
                if($this->attachmentsHaveMultipart('related')){
                    $relBoundary = uniqid('B_REL_', true);
                    $relBoundaryHeader = 'Content-Type: multipart/related; boundary="' . $relBoundary . '"';
                    if(isset($lastBoundary)){
                        $body .= '--' . $lastBoundary . $this->newline . $relBoundaryHeader;
                    }else{
                        $hdr .= $relBoundary;
                    }
                    $lastBoundary = $relBoundary;
                }
                if($this->getProtocol() === 'mail'){
                    $this->headerStr .= $hdr;
                }

                mb_strlen($body, '8bit') && $body .= $this->newline . $this->newline;
                $body .= $this->getMimeMessage()
                    . $this->newline . $this->newline
                    . '--' . $lastBoundary . $this->newline
                    . 'Content-Type: multipart/alternative; boundary="' . $altBoundary . '"'
                    . $this->newline . $this->newline
                    . '--' . $altBoundary . $this->newline
                    . 'Content-Type: text/plain; charset='
                    . $this->charset . $this->newline
                    . 'Content-Transfer-Encoding: '
                    . $this->getEncoding()
                    . $this->newline . $this->newline
                    . $this->getAltMessage()
                    . $this->newline . $this->newline
                    . '--' . $altBoundary . $this->newline
                    . 'Content-Type: text/html; charset='
                    . $this->charset . $this->newline
                    . 'Content-Transfer-Encoding: quoted-printable'
                    . $this->newline . $this->newline
                    . $this->prepQuotedPrintable($this->body)
                    . $this->newline . $this->newline
                    . '--' . $altBoundary . '--'
                    . $this->newline . $this->newline;

                if(!empty($relBoundary)){
                    $body .= $this->newline . $this->newline;
                    $this->appendAttachments($body, $relBoundary, 'related');
                }
                if(!empty($atcBoundary)){
                    $body .= $this->newline . $this->newline;
                    $this->appendAttachments($body, $atcBoundary, 'mixed');
                }
                break;
        }
        $this->finalBody = ($this->getProtocol() === 'mail') ? $body : $hdr . $this->newline . $this->newline . $body;
    }

    protected function attachmentsHaveMultipart($type): bool
    {
        foreach ($this->attachments as &$attachment) {
            if($attachment['multipart'] === $type){
                return true;
            }
        }
        return false;
    }

    protected function appendAttachments(string &$body, string $boundary, ?string $multipart = null): void
    {
        foreach ($this->attachments as $attachment) {
            if(isset($multipart) && $attachment['multipart'] !== $multipart){
                continue;
            }
            $name = $attachment['name'][1] ?? basename($attachment['name'][0]);
            $body .= '--' . $boundary . $this->newline
                . 'Content-Type: ' . $attachment['type'] . '; '
                . 'name="' . $name . '"' . $this->newline
                . 'Content-Disposition: '
                . $attachment['disposition'] . ';' . $this->newline
                . 'Content-Transfer-Encoding: base64' . $this->newline
                . ((!empty($attachment['cid'])) ? 'Content-ID: <' . $attachment['cid'] . '>' . $this->newline : '')
                . $this->newline
                . $attachment['content'] . $this->newline;
        }
        if(!empty($name)){
            $body .= '--' . $boundary . '--';
        }
    }

    protected function prepQuotedPrintable(string $str): string
    {
        static $asciiSafeChars = [
            39, 40, 41, 42, 43, 44, 45, 46, 47, 58, 61, 63,
            48, 49, 50, 51, 52, 53, 54, 55, 56, 57,
            65, 66, 67, 68, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 83, 84, 85, 86, 87, 88, 89, 90,
            97, 98, 99, 100, 101, 102, 103, 104, 105, 106, 107, 108, 109, 110, 111, 112, 113, 114, 115, 116, 117, 118, 119, 120, 121, 122
        ];
        $str = str_replace(['{unwrap}', '{/unwrap}'], '', $str);
        if($this->CRLF === "\r\n"){
            return quoted_printable_encode($str);
        }
        $str = preg_replace(['| +|', '/\x00+/'], [' ', ''], $str);
        if(strpos($str, "\r") !== FALSE){
            $str = str_replace(["\r\n", "\r"], "\n", $str);
        }
        $escape = '=';
        $out = '';
        $lines = explode("\n", $str);
        foreach ($lines as $line) {
            $len = mb_strlen($line, '8bit');
            $temp = '';
            for ($i = 0; $i < $len; $i++) {
                $char = $line[$i];
                $ascii = ord($char);
                if($ascii === 32 || $ascii === 9){
                    if($i === ($len - 1)){
                        $char = $escape . sprintf('%02s', dechex($ascii));
                    }
                }elseif($ascii === 61 || !in_array($ascii, $asciiSafeChars, true)){
                    $char = $escape . strtoupper(sprintf('%02s', dechex($ascii)));
                }

                if((mb_strlen($temp, '8bit') + mb_strlen($char, '8bit')) >= 76){
                    $out .= $temp . $escape . $this->CRLF;
                    $temp = '';
                }
                $temp .= $char;
            }
            $out .= $temp . $this->CRLF;
        }
        return mb_substr($out, 0, mb_strlen($this->CRLF, '8bit'), '8bit');
    }

    protected function unwrapSpecials(): void
    {
        $this->finalBody = preg_replace_callback('/\{unwrap\}(.*?)\{\/unwrap\}/si', [
            $this, 'removeNLCallback'
        ], $this->finalBody);
    }

    protected function removeNLCallback($matches): string
    {
        if(strpos($matches[1], "\r") !== FALSE
            || strpos($matches[1], "\n") !== FALSE){
            $matches[1] = str_replace(["\r\n", "\r", "\n"], '', $matches[1]);
        }
        return $matches[1];
    }

    protected function spoolEmail(): bool
    {
        $this->unwrapSpecials();
        $protocol = $this->getProtocol();
        $method = 'sendWith' . ucfirst($protocol);
        try {
            $success = $this->{$method}();
        }catch (\Exception $e) {
            $success = false;
            $this->setErrorMsg('ERROR : ' . $e->getMessage());
        }
        if($success === FALSE){
            $this->setErrorMsg('Email sending failed.');
            return false;
        }
        return true;
    }

    protected function validateEmailForShell(string $mail): bool
    {
        return ((filter_var($mail, FILTER_VALIDATE_EMAIL) === $mail) && preg_match('#\A[a-z0-9._+-]+@[a-z0-9.-]{1,253}\z#i', $mail));
    }

    protected function sendWithMail(): bool
    {
        $recipients = is_array($this->recipients) ? implode(', ', $this->recipients) : $this->recipients;
        $from = $this->cleanEmail($this->headers['Return-Path']);
        if($this->validateEmailForShell($from) === FALSE){
            return mail($recipients, $this->subject, $this->finalBody, $this->headerStr);
        }
        return mail($recipients, $this->subject, $this->finalBody, $this->headerStr, '-f ' . $from);
    }

    protected function sendWithSendmail(): bool
    {
        $from = $this->cleanEmail($this->headers['From']);
        $from = $this->validateEmailForShell($from) ? '-f ' . $from : '';
        if(!function_exists('popen') || ($fp = @popen($this->mailPath . ' -oi ' . $from . ' -t', 'w')) === FALSE){
            return false;
        }
        fwrite($fp, $this->headerStr);
        fwrite($fp, $this->finalBody);
        $status = pclose($fp);
        if($status !== 0){
            $this->setErrorMsg('Email sending failed. Status : ' . $status);
            return false;
        }
        return true;
    }

    protected function sendWithSmtp(): bool
    {
        if(trim($this->SMTPHost) === ''){
            $this->setErrorMsg('SMTP server unknown.');
            return false;
        }
        if(!$this->SMTPConnect() || !$this->SMTPAuthenticate()){
            return false;
        }
        if(!$this->sendCommand('from', $this->cleanEmail($this->headers['From']))){
            $this->SMTPEnd();
            return false;
        }

        $sendEmails = [];
        if(!empty($this->recipients)){
            $sendEmails = array_merge($sendEmails, $this->recipients);
        }
        if(!empty($this->CCArray)){
            $sendEmails = array_merge($sendEmails, $this->CCArray);
        }
        if(!empty($this->BCCArray)){
            $sendEmails = array_merge($sendEmails, $this->BCCArray);
        }
        foreach ($sendEmails as $value) {
            if($value !== '' && !$this->sendCommand('to', $value)){
                $this->SMTPEnd();
                return false;
            }
        }
        if(!$this->sendCommand('data')){
            $this->SMTPEnd();
            return false;
        }
        $this->sendData($this->headerStr . preg_replace('/^\./m', '..$1', $this->finalBody));
        $this->sendData($this->newline . '.');
        $reply = $this->getSMTPData();

        if(strpos($reply, '250') !== 0){
            $this->setErrorMsg('SMTP ERROR : ' . $reply);
            return false;
        }

        return true;
    }

    protected function SMTPEnd(): void
    {
        $this->sendCommand($this->SMTPKeepAlive ? 'reset' : 'quit');
    }

    protected function SMTPConnect(): bool
    {
        if(is_resource($this->SMTPConnect)){
            return true;
        }
        $ssl = '';
        if($this->SMTPPort === 465){
            $ssl = 'tls://';
        }elseif($this->SMTPCrypto === 'ssl'){
            $ssl = 'ssl://';
        }
        $this->SMTPConnect = fsockopen(
            $ssl . $this->SMTPHost,
            $this->SMTPPort,
            $errno,
            $errStr,
            $this->SMTPTimeout
        );
        if(!is_resource($this->SMTPConnect)){
            $this->setErrorMsg('SMTP Connect ERROR ' . $errno . ': ' . $errStr);
            return false;
        }
        stream_set_timeout($this->SMTPConnect, $this->SMTPTimeout);
        $this->getSMTPData();
        if($this->SMTPCrypto === 'tls'){
            $this->sendCommand('hello');
            $this->sendCommand('starttls');
            $crypto = stream_socket_enable_crypto(
                $this->SMTPConnect,
                true,
                STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT
            );
            if($crypto !== TRUE){
                $this->setErrorMsg('SMTP ERROR : ' . (string)$this->getSMTPData());
                return false;
            }
        }
        return $this->sendCommand('hello');
    }

    protected function sendCommand(string $cmd, string $data = ''): bool
    {
        switch (strtolower($cmd)) {
            case 'hello':
                $this->sendData((($this->SMTPAuth || $this->getEncoding() === '8bit') ? 'EHLO' : 'HELO') . ' ' . $this->getHostname());
                $resp = 250;
                break;
            case 'starttls':
                $this->sendData('STARTTLS');
                $resp = 220;
                break;
            case 'from':
                $this->sendData('MAIL FROM:<' . $data . '>');
                $resp = 250;
                break;
            case 'to':
                $this->sendData(($this->DSN ? 'RCPT TO:<' . $data . '> NOTIFY=SUCCESS,DELAY,FAILURE ORCPT=rfc822;' . $data : 'RCPT TO:<' . $data . '>'));
                $resp = 250;
                break;
            case 'data':
                $this->sendData('DATA');
                $resp = 354;
                break;
            case 'reset':
                $this->sendData('RSET');
                $resp = 250;
                break;
            case 'quit':
                $this->sendData('QUIT');
                $rest = 221;
                break;
            default:
                $resp = null;
        }
        $reply = $this->getSMTPData();
        if($resp === null || ((int)mb_substr($reply, 0, 3, '8bit') !== $resp)){
            $this->setErrorMsg('SMTP Error : ' . $reply);
            return false;
        }
        if(strtolower($cmd) === 'quit'){
            fclose($this->SMTPConnect);
        }

        return true;
    }

    protected function SMTPAuthenticate(): bool
    {
        if($this->SMTPAuth === FALSE){
            return true;
        }
        if($this->SMTPUser === '' && $this->SMTPPass === ''){
            $this->setErrorMsg('SMTP username and password unknown.');
            return false;
        }
        $this->sendData('AUTH LOGIN');
        $reply = $this->getSMTPData();
        if(strpos($reply, '503') === 0){
            return true;
        }
        if(strpos($reply, '334') !== 0){
            $this->setErrorMsg('SMTP connection failed. ' . $reply);
            return false;
        }
        $this->sendData(base64_encode($this->SMTPUser));
        $reply = $this->getSMTPData();
        if(strpos($reply, '334') !== 0){
            $this->setErrorMsg('Failed to verify SMTP username information.' . $reply);
            return false;
        }
        $this->sendData(base64_encode($this->SMTPPass));
        $reply = $this->getSMTPData();
        if(strpos($reply, '235') !== 0){
            $this->setErrorMsg('Failed to verify SMTP password information.' . $reply);
            return false;
        }
        if($this->SMTPKeepAlive === TRUE){
            $this->SMTPAuth = false;
        }

        return true;
    }

    protected function sendData(string $data): bool
    {
        $data .= $this->newline;
        $res = null;

        $len = mb_strlen($data, '8bit');
        for($written = $timestamp = 0; $written < $len; $written += $res){
            if(($res = fwrite($this->SMTPConnect, mb_substr($data, $written, null, '8bit'))) === FALSE){
                break;
            }
            if($res === 0){
                if($timestamp === 0){
                    $timestamp = time();
                }elseif($timestamp < (time() - $this->SMTPTimeout)){
                    $res = false;
                    break;
                }
                usleep(250000);
                continue;
            }

            $timestamp = 0;
        }

        if(!is_int($res)){
            $this->setErrorMsg('Failed to send SMTP command.' . $data);
            return false;
        }
        return true;
    }

    protected function getSMTPData(): string
    {
        $data = '';
        while($str = fgets($this->SMTPConnect, 512)){
            $data .= $str;
            if($str[3] === ' '){
                break;
            }
        }
        return $data;
    }

    protected function getHostname(): string
    {
        return $_SERVER['SERVER_NAME'] ?? (isset($_SERVER['SERVER_ADDR']) ? '[' . $_SERVER['SERVER_ADDR'] . ']' : '[127.0.0.1]');
    }

    protected function setArchiveValues(): array
    {
        $this->archive = array_merge(get_object_vars($this), $this->tmpArchive);
        unset($this->archive['archive']);
        $this->tmpArchive = [];
        return $this->archive;
    }
}
