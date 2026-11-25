<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\RespondingSocketClient;
use InitPHP\Mailer\Transport\MailTransport;
use InitPHP\Mailer\Transport\SendmailTransport;
use InitPHP\Mailer\Transport\SmtpTransport;
use InitPHP\Mailer\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function count;
use function explode;
use function preg_match;
use function sort;

/**
 * A custom To, Cc or Bcc header set with setHeader(), across the three
 * transports, with and without BCC batching, and for six spellings of the name
 * (exact, lower, upper, mixed case, and a space before or after it).
 *
 * Each row holds the To/Cc/Bcc header lines of every message as they are now and
 * as 3.0.0 produced them for the same calls; the 3.0.0 column was recorded by
 * running the 3.0.0 tag's source. `To` is not special-cased: its output equals
 * 3.0.0's in every row. The only difference is on `Cc` and `Bcc`: when the
 * library writes that header itself, a custom one of the same name (any case or
 * spacing) is no longer emitted as a second line.
 */
final class RecipientHeaderMatrixTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool, bool, string, list<list<string>>, list<list<string>>}>
     */
    public static function matrix(): array
    {
        return [
            'mail, To, library writes it, one message, custom \'To\'' => ['mail', 'To', true, false, 'To', [['To: custom@co.example']], [['To: custom@co.example']]],
            'mail, To, library writes it, one message, custom \'to\'' => ['mail', 'To', true, false, 'to', [['to: custom@co.example']], [['to: custom@co.example']]],
            'mail, To, library writes it, one message, custom \'TO\'' => ['mail', 'To', true, false, 'TO', [['TO: custom@co.example']], [['TO: custom@co.example']]],
            'mail, To, library writes it, one message, custom \'tO\'' => ['mail', 'To', true, false, 'tO', [['tO: custom@co.example']], [['tO: custom@co.example']]],
            'mail, To, library writes it, one message, custom \'To \'' => ['mail', 'To', true, false, 'To ', [['To : custom@co.example']], [['To : custom@co.example']]],
            'mail, To, library writes it, one message, custom \' To\'' => ['mail', 'To', true, false, ' To', [[' To: custom@co.example']], [[' To: custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \'To\'' => ['mail', 'To', true, true, 'To', [['Bcc: b1@x.com, b2@x.com', 'To: custom@co.example'], ['Bcc: b3@x.com', 'To: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: custom@co.example'], ['Bcc: b3@x.com', 'To: custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \'to\'' => ['mail', 'To', true, true, 'to', [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \'TO\'' => ['mail', 'To', true, true, 'TO', [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \'tO\'' => ['mail', 'To', true, true, 'tO', [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \'To \'' => ['mail', 'To', true, true, 'To ', [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']]],
            'mail, To, library writes it, BCC batches, custom \' To\'' => ['mail', 'To', true, true, ' To', [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']], [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']]],
            'mail, To, library does not, one message, custom \'To\'' => ['mail', 'To', false, false, 'To', [['Bcc: b@x.com', 'To: custom@co.example']], [['Bcc: b@x.com', 'To: custom@co.example']]],
            'mail, To, library does not, one message, custom \'to\'' => ['mail', 'To', false, false, 'to', [['Bcc: b@x.com', 'to: custom@co.example']], [['Bcc: b@x.com', 'to: custom@co.example']]],
            'mail, To, library does not, one message, custom \'TO\'' => ['mail', 'To', false, false, 'TO', [['Bcc: b@x.com', 'TO: custom@co.example']], [['Bcc: b@x.com', 'TO: custom@co.example']]],
            'mail, To, library does not, one message, custom \'tO\'' => ['mail', 'To', false, false, 'tO', [['Bcc: b@x.com', 'tO: custom@co.example']], [['Bcc: b@x.com', 'tO: custom@co.example']]],
            'mail, To, library does not, one message, custom \'To \'' => ['mail', 'To', false, false, 'To ', [['Bcc: b@x.com', 'To : custom@co.example']], [['Bcc: b@x.com', 'To : custom@co.example']]],
            'mail, To, library does not, one message, custom \' To\'' => ['mail', 'To', false, false, ' To', [[' To: custom@co.example', 'Bcc: b@x.com']], [[' To: custom@co.example', 'Bcc: b@x.com']]],
            'mail, To, library does not, BCC batches, custom \'To\'' => ['mail', 'To', false, true, 'To', [['Bcc: b1@x.com, b2@x.com', 'To: custom@co.example'], ['Bcc: b3@x.com', 'To: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: custom@co.example'], ['Bcc: b3@x.com', 'To: custom@co.example']]],
            'mail, To, library does not, BCC batches, custom \'to\'' => ['mail', 'To', false, true, 'to', [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']]],
            'mail, To, library does not, BCC batches, custom \'TO\'' => ['mail', 'To', false, true, 'TO', [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']]],
            'mail, To, library does not, BCC batches, custom \'tO\'' => ['mail', 'To', false, true, 'tO', [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']]],
            'mail, To, library does not, BCC batches, custom \'To \'' => ['mail', 'To', false, true, 'To ', [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']]],
            'mail, To, library does not, BCC batches, custom \' To\'' => ['mail', 'To', false, true, ' To', [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']], [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']]],
            'mail, Cc, library writes it, one message, custom \'Cc\'' => ['mail', 'Cc', true, false, 'Cc', [['Cc: c@x.com']], [['Cc: c@x.com']]],
            'mail, Cc, library writes it, one message, custom \'cc\'' => ['mail', 'Cc', true, false, 'cc', [['Cc: c@x.com']], [['Cc: c@x.com', 'cc: custom@co.example']]],
            'mail, Cc, library writes it, one message, custom \'CC\'' => ['mail', 'Cc', true, false, 'CC', [['Cc: c@x.com']], [['CC: custom@co.example', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, one message, custom \'cC\'' => ['mail', 'Cc', true, false, 'cC', [['Cc: c@x.com']], [['Cc: c@x.com', 'cC: custom@co.example']]],
            'mail, Cc, library writes it, one message, custom \'Cc \'' => ['mail', 'Cc', true, false, 'Cc ', [['Cc: c@x.com']], [['Cc : custom@co.example', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, one message, custom \' Cc\'' => ['mail', 'Cc', true, false, ' Cc', [['Cc: c@x.com']], [[' Cc: custom@co.example', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, BCC batches, custom \'Cc\'' => ['mail', 'Cc', true, true, 'Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, BCC batches, custom \'cc\'' => ['mail', 'Cc', true, true, 'cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'Cc: c@x.com', 'cc: custom@co.example']]],
            'mail, Cc, library writes it, BCC batches, custom \'CC\'' => ['mail', 'Cc', true, true, 'CC', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'CC: custom@co.example', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, BCC batches, custom \'cC\'' => ['mail', 'Cc', true, true, 'cC', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'Cc: c@x.com', 'cC: custom@co.example']]],
            'mail, Cc, library writes it, BCC batches, custom \'Cc \'' => ['mail', 'Cc', true, true, 'Cc ', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc : custom@co.example', 'Cc: c@x.com']]],
            'mail, Cc, library writes it, BCC batches, custom \' Cc\'' => ['mail', 'Cc', true, true, ' Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com', 'Cc: c@x.com']]],
            'mail, Cc, library does not, one message, custom \'Cc\'' => ['mail', 'Cc', false, false, 'Cc', [['Cc: custom@co.example']], [['Cc: custom@co.example']]],
            'mail, Cc, library does not, one message, custom \'cc\'' => ['mail', 'Cc', false, false, 'cc', [['cc: custom@co.example']], [['cc: custom@co.example']]],
            'mail, Cc, library does not, one message, custom \'CC\'' => ['mail', 'Cc', false, false, 'CC', [['CC: custom@co.example']], [['CC: custom@co.example']]],
            'mail, Cc, library does not, one message, custom \'cC\'' => ['mail', 'Cc', false, false, 'cC', [['cC: custom@co.example']], [['cC: custom@co.example']]],
            'mail, Cc, library does not, one message, custom \'Cc \'' => ['mail', 'Cc', false, false, 'Cc ', [['Cc : custom@co.example']], [['Cc : custom@co.example']]],
            'mail, Cc, library does not, one message, custom \' Cc\'' => ['mail', 'Cc', false, false, ' Cc', [[' Cc: custom@co.example']], [[' Cc: custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \'Cc\'' => ['mail', 'Cc', false, true, 'Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: custom@co.example'], ['Bcc: b3@x.com', 'Cc: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'Cc: custom@co.example'], ['Bcc: b3@x.com', 'Cc: custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \'cc\'' => ['mail', 'Cc', false, true, 'cc', [['Bcc: b1@x.com, b2@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'cc: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'cc: custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \'CC\'' => ['mail', 'Cc', false, true, 'CC', [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example'], ['Bcc: b3@x.com', 'CC: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example'], ['Bcc: b3@x.com', 'CC: custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \'cC\'' => ['mail', 'Cc', false, true, 'cC', [['Bcc: b1@x.com, b2@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'cC: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'cC: custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \'Cc \'' => ['mail', 'Cc', false, true, 'Cc ', [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example'], ['Bcc: b3@x.com', 'Cc : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example'], ['Bcc: b3@x.com', 'Cc : custom@co.example']]],
            'mail, Cc, library does not, BCC batches, custom \' Cc\'' => ['mail', 'Cc', false, true, ' Cc', [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com']], [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com']]],
            'mail, Bcc, library writes it, one message, custom \'Bcc\'' => ['mail', 'Bcc', true, false, 'Bcc', [['Bcc: b@x.com']], [['Bcc: b@x.com']]],
            'mail, Bcc, library writes it, one message, custom \'bcc\'' => ['mail', 'Bcc', true, false, 'bcc', [['Bcc: b@x.com']], [['Bcc: b@x.com', 'bcc: custom@co.example']]],
            'mail, Bcc, library writes it, one message, custom \'BCC\'' => ['mail', 'Bcc', true, false, 'BCC', [['Bcc: b@x.com']], [['BCC: custom@co.example', 'Bcc: b@x.com']]],
            'mail, Bcc, library writes it, one message, custom \'bCC\'' => ['mail', 'Bcc', true, false, 'bCC', [['Bcc: b@x.com']], [['Bcc: b@x.com', 'bCC: custom@co.example']]],
            'mail, Bcc, library writes it, one message, custom \'Bcc \'' => ['mail', 'Bcc', true, false, 'Bcc ', [['Bcc: b@x.com']], [['Bcc : custom@co.example', 'Bcc: b@x.com']]],
            'mail, Bcc, library writes it, one message, custom \' Bcc\'' => ['mail', 'Bcc', true, false, ' Bcc', [['Bcc: b@x.com']], [[' Bcc: custom@co.example', 'Bcc: b@x.com']]],
            'mail, Bcc, library writes it, BCC batches, custom \'Bcc\'' => ['mail', 'Bcc', true, true, 'Bcc', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']]],
            'mail, Bcc, library writes it, BCC batches, custom \'bcc\'' => ['mail', 'Bcc', true, true, 'bcc', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'bcc: custom@co.example'], ['Bcc: b3@x.com', 'bcc: custom@co.example']]],
            'mail, Bcc, library writes it, BCC batches, custom \'BCC\'' => ['mail', 'Bcc', true, true, 'BCC', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['BCC: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], ['BCC: custom@co.example', 'Bcc: b3@x.com']]],
            'mail, Bcc, library writes it, BCC batches, custom \'bCC\'' => ['mail', 'Bcc', true, true, 'bCC', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'bCC: custom@co.example'], ['Bcc: b3@x.com', 'bCC: custom@co.example']]],
            'mail, Bcc, library writes it, BCC batches, custom \'Bcc \'' => ['mail', 'Bcc', true, true, 'Bcc ', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['Bcc : custom@co.example', 'Bcc: b1@x.com, b2@x.com'], ['Bcc : custom@co.example', 'Bcc: b3@x.com']]],
            'mail, Bcc, library writes it, BCC batches, custom \' Bcc\'' => ['mail', 'Bcc', true, true, ' Bcc', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [[' Bcc: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' Bcc: custom@co.example', 'Bcc: b3@x.com']]],
            'mail, Bcc, library does not, one message, custom \'Bcc\'' => ['mail', 'Bcc', false, false, 'Bcc', [['Bcc: custom@co.example']], [['Bcc: custom@co.example']]],
            'mail, Bcc, library does not, one message, custom \'bcc\'' => ['mail', 'Bcc', false, false, 'bcc', [['bcc: custom@co.example']], [['bcc: custom@co.example']]],
            'mail, Bcc, library does not, one message, custom \'BCC\'' => ['mail', 'Bcc', false, false, 'BCC', [['BCC: custom@co.example']], [['BCC: custom@co.example']]],
            'mail, Bcc, library does not, one message, custom \'bCC\'' => ['mail', 'Bcc', false, false, 'bCC', [['bCC: custom@co.example']], [['bCC: custom@co.example']]],
            'mail, Bcc, library does not, one message, custom \'Bcc \'' => ['mail', 'Bcc', false, false, 'Bcc ', [['Bcc : custom@co.example']], [['Bcc : custom@co.example']]],
            'mail, Bcc, library does not, one message, custom \' Bcc\'' => ['mail', 'Bcc', false, false, ' Bcc', [[' Bcc: custom@co.example']], [[' Bcc: custom@co.example']]],
            'sendmail, To, library writes it, one message, custom \'To\'' => ['sendmail', 'To', true, false, 'To', [['To: t@x.com']], [['To: t@x.com']]],
            'sendmail, To, library writes it, one message, custom \'to\'' => ['sendmail', 'To', true, false, 'to', [['To: t@x.com', 'to: custom@co.example']], [['To: t@x.com', 'to: custom@co.example']]],
            'sendmail, To, library writes it, one message, custom \'TO\'' => ['sendmail', 'To', true, false, 'TO', [['TO: custom@co.example', 'To: t@x.com']], [['TO: custom@co.example', 'To: t@x.com']]],
            'sendmail, To, library writes it, one message, custom \'tO\'' => ['sendmail', 'To', true, false, 'tO', [['To: t@x.com', 'tO: custom@co.example']], [['To: t@x.com', 'tO: custom@co.example']]],
            'sendmail, To, library writes it, one message, custom \'To \'' => ['sendmail', 'To', true, false, 'To ', [['To : custom@co.example', 'To: t@x.com']], [['To : custom@co.example', 'To: t@x.com']]],
            'sendmail, To, library writes it, one message, custom \' To\'' => ['sendmail', 'To', true, false, ' To', [[' To: custom@co.example', 'To: t@x.com']], [[' To: custom@co.example', 'To: t@x.com']]],
            'sendmail, To, library writes it, BCC batches, custom \'To\'' => ['sendmail', 'To', true, true, 'To', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']]],
            'sendmail, To, library writes it, BCC batches, custom \'to\'' => ['sendmail', 'To', true, true, 'to', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']]],
            'sendmail, To, library writes it, BCC batches, custom \'TO\'' => ['sendmail', 'To', true, true, 'TO', [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'TO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'TO: custom@co.example']]],
            'sendmail, To, library writes it, BCC batches, custom \'tO\'' => ['sendmail', 'To', true, true, 'tO', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']]],
            'sendmail, To, library writes it, BCC batches, custom \'To \'' => ['sendmail', 'To', true, true, 'To ', [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'To : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'To : custom@co.example']]],
            'sendmail, To, library writes it, BCC batches, custom \' To\'' => ['sendmail', 'To', true, true, ' To', [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']], [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, To, library does not, one message, custom \'To\'' => ['sendmail', 'To', false, false, 'To', [['Bcc: b@x.com']], [['Bcc: b@x.com']]],
            'sendmail, To, library does not, one message, custom \'to\'' => ['sendmail', 'To', false, false, 'to', [['Bcc: b@x.com', 'to: custom@co.example']], [['Bcc: b@x.com', 'to: custom@co.example']]],
            'sendmail, To, library does not, one message, custom \'TO\'' => ['sendmail', 'To', false, false, 'TO', [['Bcc: b@x.com', 'TO: custom@co.example']], [['Bcc: b@x.com', 'TO: custom@co.example']]],
            'sendmail, To, library does not, one message, custom \'tO\'' => ['sendmail', 'To', false, false, 'tO', [['Bcc: b@x.com', 'tO: custom@co.example']], [['Bcc: b@x.com', 'tO: custom@co.example']]],
            'sendmail, To, library does not, one message, custom \'To \'' => ['sendmail', 'To', false, false, 'To ', [['Bcc: b@x.com', 'To : custom@co.example']], [['Bcc: b@x.com', 'To : custom@co.example']]],
            'sendmail, To, library does not, one message, custom \' To\'' => ['sendmail', 'To', false, false, ' To', [[' To: custom@co.example', 'Bcc: b@x.com']], [[' To: custom@co.example', 'Bcc: b@x.com']]],
            'sendmail, To, library does not, BCC batches, custom \'To\'' => ['sendmail', 'To', false, true, 'To', [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com'], ['Bcc: b3@x.com']]],
            'sendmail, To, library does not, BCC batches, custom \'to\'' => ['sendmail', 'To', false, true, 'to', [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'to: custom@co.example'], ['Bcc: b3@x.com', 'to: custom@co.example']]],
            'sendmail, To, library does not, BCC batches, custom \'TO\'' => ['sendmail', 'To', false, true, 'TO', [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'TO: custom@co.example'], ['Bcc: b3@x.com', 'TO: custom@co.example']]],
            'sendmail, To, library does not, BCC batches, custom \'tO\'' => ['sendmail', 'To', false, true, 'tO', [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'tO: custom@co.example'], ['Bcc: b3@x.com', 'tO: custom@co.example']]],
            'sendmail, To, library does not, BCC batches, custom \'To \'' => ['sendmail', 'To', false, true, 'To ', [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To : custom@co.example'], ['Bcc: b3@x.com', 'To : custom@co.example']]],
            'sendmail, To, library does not, BCC batches, custom \' To\'' => ['sendmail', 'To', false, true, ' To', [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']], [[' To: custom@co.example', 'Bcc: b1@x.com, b2@x.com'], [' To: custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, Cc, library writes it, one message, custom \'Cc\'' => ['sendmail', 'Cc', true, false, 'Cc', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com']]],
            'sendmail, Cc, library writes it, one message, custom \'cc\'' => ['sendmail', 'Cc', true, false, 'cc', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example']]],
            'sendmail, Cc, library writes it, one message, custom \'CC\'' => ['sendmail', 'Cc', true, false, 'CC', [['Cc: c@x.com', 'To: t@x.com']], [['CC: custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'sendmail, Cc, library writes it, one message, custom \'cC\'' => ['sendmail', 'Cc', true, false, 'cC', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cC: custom@co.example']]],
            'sendmail, Cc, library writes it, one message, custom \'Cc \'' => ['sendmail', 'Cc', true, false, 'Cc ', [['Cc: c@x.com', 'To: t@x.com']], [['Cc : custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'sendmail, Cc, library writes it, one message, custom \' Cc\'' => ['sendmail', 'Cc', true, false, ' Cc', [['Cc: c@x.com', 'To: t@x.com']], [[' Cc: custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'sendmail, Cc, library writes it, BCC batches, custom \'Cc\'' => ['sendmail', 'Cc', true, true, 'Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']]],
            'sendmail, Cc, library writes it, BCC batches, custom \'cc\'' => ['sendmail', 'Cc', true, true, 'cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'Cc: c@x.com', 'cc: custom@co.example']]],
            'sendmail, Cc, library writes it, BCC batches, custom \'CC\'' => ['sendmail', 'Cc', true, true, 'CC', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'CC: custom@co.example', 'Cc: c@x.com']]],
            'sendmail, Cc, library writes it, BCC batches, custom \'cC\'' => ['sendmail', 'Cc', true, true, 'cC', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'Cc: c@x.com', 'cC: custom@co.example']]],
            'sendmail, Cc, library writes it, BCC batches, custom \'Cc \'' => ['sendmail', 'Cc', true, true, 'Cc ', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc : custom@co.example', 'Cc: c@x.com']]],
            'sendmail, Cc, library writes it, BCC batches, custom \' Cc\'' => ['sendmail', 'Cc', true, true, ' Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: c@x.com']], [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'Cc: c@x.com', 'To: t@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com', 'Cc: c@x.com']]],
            'sendmail, Cc, library does not, one message, custom \'Cc\'' => ['sendmail', 'Cc', false, false, 'Cc', [['Cc: custom@co.example', 'To: t@x.com']], [['Cc: custom@co.example', 'To: t@x.com']]],
            'sendmail, Cc, library does not, one message, custom \'cc\'' => ['sendmail', 'Cc', false, false, 'cc', [['To: t@x.com', 'cc: custom@co.example']], [['To: t@x.com', 'cc: custom@co.example']]],
            'sendmail, Cc, library does not, one message, custom \'CC\'' => ['sendmail', 'Cc', false, false, 'CC', [['CC: custom@co.example', 'To: t@x.com']], [['CC: custom@co.example', 'To: t@x.com']]],
            'sendmail, Cc, library does not, one message, custom \'cC\'' => ['sendmail', 'Cc', false, false, 'cC', [['To: t@x.com', 'cC: custom@co.example']], [['To: t@x.com', 'cC: custom@co.example']]],
            'sendmail, Cc, library does not, one message, custom \'Cc \'' => ['sendmail', 'Cc', false, false, 'Cc ', [['Cc : custom@co.example', 'To: t@x.com']], [['Cc : custom@co.example', 'To: t@x.com']]],
            'sendmail, Cc, library does not, one message, custom \' Cc\'' => ['sendmail', 'Cc', false, false, ' Cc', [[' Cc: custom@co.example', 'To: t@x.com']], [[' Cc: custom@co.example', 'To: t@x.com']]],
            'sendmail, Cc, library does not, BCC batches, custom \'Cc\'' => ['sendmail', 'Cc', false, true, 'Cc', [['Bcc: b1@x.com, b2@x.com', 'Cc: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'Cc: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc: custom@co.example']]],
            'sendmail, Cc, library does not, BCC batches, custom \'cc\'' => ['sendmail', 'Cc', false, true, 'cc', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'cc: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'cc: custom@co.example'], ['Bcc: b3@x.com', 'cc: custom@co.example']]],
            'sendmail, Cc, library does not, BCC batches, custom \'CC\'' => ['sendmail', 'Cc', false, true, 'CC', [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'CC: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'CC: custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'CC: custom@co.example']]],
            'sendmail, Cc, library does not, BCC batches, custom \'cC\'' => ['sendmail', 'Cc', false, true, 'cC', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'cC: custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'cC: custom@co.example'], ['Bcc: b3@x.com', 'cC: custom@co.example']]],
            'sendmail, Cc, library does not, BCC batches, custom \'Cc \'' => ['sendmail', 'Cc', false, true, 'Cc ', [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc : custom@co.example']], [['Bcc: b1@x.com, b2@x.com', 'Cc : custom@co.example', 'To: t@x.com'], ['Bcc: b3@x.com', 'Cc : custom@co.example']]],
            'sendmail, Cc, library does not, BCC batches, custom \' Cc\'' => ['sendmail', 'Cc', false, true, ' Cc', [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com']], [[' Cc: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], [' Cc: custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, Bcc, library writes it, one message, custom \'Bcc\'' => ['sendmail', 'Bcc', true, false, 'Bcc', [['Bcc: b@x.com', 'To: t@x.com']], [['Bcc: b@x.com', 'To: t@x.com']]],
            'sendmail, Bcc, library writes it, one message, custom \'bcc\'' => ['sendmail', 'Bcc', true, false, 'bcc', [['Bcc: b@x.com', 'To: t@x.com']], [['Bcc: b@x.com', 'To: t@x.com', 'bcc: custom@co.example']]],
            'sendmail, Bcc, library writes it, one message, custom \'BCC\'' => ['sendmail', 'Bcc', true, false, 'BCC', [['Bcc: b@x.com', 'To: t@x.com']], [['BCC: custom@co.example', 'Bcc: b@x.com', 'To: t@x.com']]],
            'sendmail, Bcc, library writes it, one message, custom \'bCC\'' => ['sendmail', 'Bcc', true, false, 'bCC', [['Bcc: b@x.com', 'To: t@x.com']], [['Bcc: b@x.com', 'To: t@x.com', 'bCC: custom@co.example']]],
            'sendmail, Bcc, library writes it, one message, custom \'Bcc \'' => ['sendmail', 'Bcc', true, false, 'Bcc ', [['Bcc: b@x.com', 'To: t@x.com']], [['Bcc : custom@co.example', 'Bcc: b@x.com', 'To: t@x.com']]],
            'sendmail, Bcc, library writes it, one message, custom \' Bcc\'' => ['sendmail', 'Bcc', true, false, ' Bcc', [['Bcc: b@x.com', 'To: t@x.com']], [[' Bcc: custom@co.example', 'Bcc: b@x.com', 'To: t@x.com']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \'Bcc\'' => ['sendmail', 'Bcc', true, true, 'Bcc', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \'bcc\'' => ['sendmail', 'Bcc', true, true, 'bcc', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'bcc: custom@co.example'], ['Bcc: b3@x.com', 'bcc: custom@co.example']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \'BCC\'' => ['sendmail', 'Bcc', true, true, 'BCC', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['BCC: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['BCC: custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \'bCC\'' => ['sendmail', 'Bcc', true, true, 'bCC', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com', 'bCC: custom@co.example'], ['Bcc: b3@x.com', 'bCC: custom@co.example']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \'Bcc \'' => ['sendmail', 'Bcc', true, true, 'Bcc ', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [['Bcc : custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc : custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, Bcc, library writes it, BCC batches, custom \' Bcc\'' => ['sendmail', 'Bcc', true, true, ' Bcc', [['Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], ['Bcc: b3@x.com']], [[' Bcc: custom@co.example', 'Bcc: b1@x.com, b2@x.com', 'To: t@x.com'], [' Bcc: custom@co.example', 'Bcc: b3@x.com']]],
            'sendmail, Bcc, library does not, one message, custom \'Bcc\'' => ['sendmail', 'Bcc', false, false, 'Bcc', [['Bcc: custom@co.example', 'To: t@x.com']], [['Bcc: custom@co.example', 'To: t@x.com']]],
            'sendmail, Bcc, library does not, one message, custom \'bcc\'' => ['sendmail', 'Bcc', false, false, 'bcc', [['To: t@x.com', 'bcc: custom@co.example']], [['To: t@x.com', 'bcc: custom@co.example']]],
            'sendmail, Bcc, library does not, one message, custom \'BCC\'' => ['sendmail', 'Bcc', false, false, 'BCC', [['BCC: custom@co.example', 'To: t@x.com']], [['BCC: custom@co.example', 'To: t@x.com']]],
            'sendmail, Bcc, library does not, one message, custom \'bCC\'' => ['sendmail', 'Bcc', false, false, 'bCC', [['To: t@x.com', 'bCC: custom@co.example']], [['To: t@x.com', 'bCC: custom@co.example']]],
            'sendmail, Bcc, library does not, one message, custom \'Bcc \'' => ['sendmail', 'Bcc', false, false, 'Bcc ', [['Bcc : custom@co.example', 'To: t@x.com']], [['Bcc : custom@co.example', 'To: t@x.com']]],
            'sendmail, Bcc, library does not, one message, custom \' Bcc\'' => ['sendmail', 'Bcc', false, false, ' Bcc', [[' Bcc: custom@co.example', 'To: t@x.com']], [[' Bcc: custom@co.example', 'To: t@x.com']]],
            'smtp, To, library writes it, one message, custom \'To\'' => ['smtp', 'To', true, false, 'To', [['To: t@x.com']], [['To: t@x.com']]],
            'smtp, To, library writes it, one message, custom \'to\'' => ['smtp', 'To', true, false, 'to', [['To: t@x.com', 'to: custom@co.example']], [['To: t@x.com', 'to: custom@co.example']]],
            'smtp, To, library writes it, one message, custom \'TO\'' => ['smtp', 'To', true, false, 'TO', [['TO: custom@co.example', 'To: t@x.com']], [['TO: custom@co.example', 'To: t@x.com']]],
            'smtp, To, library writes it, one message, custom \'tO\'' => ['smtp', 'To', true, false, 'tO', [['To: t@x.com', 'tO: custom@co.example']], [['To: t@x.com', 'tO: custom@co.example']]],
            'smtp, To, library writes it, one message, custom \'To \'' => ['smtp', 'To', true, false, 'To ', [['To : custom@co.example', 'To: t@x.com']], [['To : custom@co.example', 'To: t@x.com']]],
            'smtp, To, library writes it, one message, custom \' To\'' => ['smtp', 'To', true, false, ' To', [[' To: custom@co.example', 'To: t@x.com']], [[' To: custom@co.example', 'To: t@x.com']]],
            'smtp, To, library writes it, BCC batches, custom \'To\'' => ['smtp', 'To', true, true, 'To', [['To: t@x.com'], []], [['To: t@x.com'], []]],
            'smtp, To, library writes it, BCC batches, custom \'to\'' => ['smtp', 'To', true, true, 'to', [['To: t@x.com', 'to: custom@co.example'], ['to: custom@co.example']], [['To: t@x.com', 'to: custom@co.example'], ['to: custom@co.example']]],
            'smtp, To, library writes it, BCC batches, custom \'TO\'' => ['smtp', 'To', true, true, 'TO', [['TO: custom@co.example', 'To: t@x.com'], ['TO: custom@co.example']], [['TO: custom@co.example', 'To: t@x.com'], ['TO: custom@co.example']]],
            'smtp, To, library writes it, BCC batches, custom \'tO\'' => ['smtp', 'To', true, true, 'tO', [['To: t@x.com', 'tO: custom@co.example'], ['tO: custom@co.example']], [['To: t@x.com', 'tO: custom@co.example'], ['tO: custom@co.example']]],
            'smtp, To, library writes it, BCC batches, custom \'To \'' => ['smtp', 'To', true, true, 'To ', [['To : custom@co.example', 'To: t@x.com'], ['To : custom@co.example']], [['To : custom@co.example', 'To: t@x.com'], ['To : custom@co.example']]],
            'smtp, To, library writes it, BCC batches, custom \' To\'' => ['smtp', 'To', true, true, ' To', [[' To: custom@co.example', 'To: t@x.com'], [' To: custom@co.example']], [[' To: custom@co.example', 'To: t@x.com'], [' To: custom@co.example']]],
            'smtp, To, library does not, one message, custom \'To\'' => ['smtp', 'To', false, false, 'To', [[]], [[]]],
            'smtp, To, library does not, one message, custom \'to\'' => ['smtp', 'To', false, false, 'to', [['to: custom@co.example']], [['to: custom@co.example']]],
            'smtp, To, library does not, one message, custom \'TO\'' => ['smtp', 'To', false, false, 'TO', [['TO: custom@co.example']], [['TO: custom@co.example']]],
            'smtp, To, library does not, one message, custom \'tO\'' => ['smtp', 'To', false, false, 'tO', [['tO: custom@co.example']], [['tO: custom@co.example']]],
            'smtp, To, library does not, one message, custom \'To \'' => ['smtp', 'To', false, false, 'To ', [['To : custom@co.example']], [['To : custom@co.example']]],
            'smtp, To, library does not, one message, custom \' To\'' => ['smtp', 'To', false, false, ' To', [[' To: custom@co.example']], [[' To: custom@co.example']]],
            'smtp, To, library does not, BCC batches, custom \'To\'' => ['smtp', 'To', false, true, 'To', [[], []], [[], []]],
            'smtp, To, library does not, BCC batches, custom \'to\'' => ['smtp', 'To', false, true, 'to', [['to: custom@co.example'], ['to: custom@co.example']], [['to: custom@co.example'], ['to: custom@co.example']]],
            'smtp, To, library does not, BCC batches, custom \'TO\'' => ['smtp', 'To', false, true, 'TO', [['TO: custom@co.example'], ['TO: custom@co.example']], [['TO: custom@co.example'], ['TO: custom@co.example']]],
            'smtp, To, library does not, BCC batches, custom \'tO\'' => ['smtp', 'To', false, true, 'tO', [['tO: custom@co.example'], ['tO: custom@co.example']], [['tO: custom@co.example'], ['tO: custom@co.example']]],
            'smtp, To, library does not, BCC batches, custom \'To \'' => ['smtp', 'To', false, true, 'To ', [['To : custom@co.example'], ['To : custom@co.example']], [['To : custom@co.example'], ['To : custom@co.example']]],
            'smtp, To, library does not, BCC batches, custom \' To\'' => ['smtp', 'To', false, true, ' To', [[' To: custom@co.example'], [' To: custom@co.example']], [[' To: custom@co.example'], [' To: custom@co.example']]],
            'smtp, Cc, library writes it, one message, custom \'Cc\'' => ['smtp', 'Cc', true, false, 'Cc', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com']]],
            'smtp, Cc, library writes it, one message, custom \'cc\'' => ['smtp', 'Cc', true, false, 'cc', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example']]],
            'smtp, Cc, library writes it, one message, custom \'CC\'' => ['smtp', 'Cc', true, false, 'CC', [['Cc: c@x.com', 'To: t@x.com']], [['CC: custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'smtp, Cc, library writes it, one message, custom \'cC\'' => ['smtp', 'Cc', true, false, 'cC', [['Cc: c@x.com', 'To: t@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cC: custom@co.example']]],
            'smtp, Cc, library writes it, one message, custom \'Cc \'' => ['smtp', 'Cc', true, false, 'Cc ', [['Cc: c@x.com', 'To: t@x.com']], [['Cc : custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'smtp, Cc, library writes it, one message, custom \' Cc\'' => ['smtp', 'Cc', true, false, ' Cc', [['Cc: c@x.com', 'To: t@x.com']], [[' Cc: custom@co.example', 'Cc: c@x.com', 'To: t@x.com']]],
            'smtp, Cc, library writes it, BCC batches, custom \'Cc\'' => ['smtp', 'Cc', true, true, 'Cc', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']]],
            'smtp, Cc, library writes it, BCC batches, custom \'cc\'' => ['smtp', 'Cc', true, true, 'cc', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example'], ['Cc: c@x.com', 'cc: custom@co.example']]],
            'smtp, Cc, library writes it, BCC batches, custom \'CC\'' => ['smtp', 'Cc', true, true, 'CC', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [['CC: custom@co.example', 'Cc: c@x.com', 'To: t@x.com'], ['CC: custom@co.example', 'Cc: c@x.com']]],
            'smtp, Cc, library writes it, BCC batches, custom \'cC\'' => ['smtp', 'Cc', true, true, 'cC', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [['Cc: c@x.com', 'To: t@x.com', 'cC: custom@co.example'], ['Cc: c@x.com', 'cC: custom@co.example']]],
            'smtp, Cc, library writes it, BCC batches, custom \'Cc \'' => ['smtp', 'Cc', true, true, 'Cc ', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [['Cc : custom@co.example', 'Cc: c@x.com', 'To: t@x.com'], ['Cc : custom@co.example', 'Cc: c@x.com']]],
            'smtp, Cc, library writes it, BCC batches, custom \' Cc\'' => ['smtp', 'Cc', true, true, ' Cc', [['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com']], [[' Cc: custom@co.example', 'Cc: c@x.com', 'To: t@x.com'], [' Cc: custom@co.example', 'Cc: c@x.com']]],
            'smtp, Cc, library does not, one message, custom \'Cc\'' => ['smtp', 'Cc', false, false, 'Cc', [['Cc: custom@co.example', 'To: t@x.com']], [['Cc: custom@co.example', 'To: t@x.com']]],
            'smtp, Cc, library does not, one message, custom \'cc\'' => ['smtp', 'Cc', false, false, 'cc', [['To: t@x.com', 'cc: custom@co.example']], [['To: t@x.com', 'cc: custom@co.example']]],
            'smtp, Cc, library does not, one message, custom \'CC\'' => ['smtp', 'Cc', false, false, 'CC', [['CC: custom@co.example', 'To: t@x.com']], [['CC: custom@co.example', 'To: t@x.com']]],
            'smtp, Cc, library does not, one message, custom \'cC\'' => ['smtp', 'Cc', false, false, 'cC', [['To: t@x.com', 'cC: custom@co.example']], [['To: t@x.com', 'cC: custom@co.example']]],
            'smtp, Cc, library does not, one message, custom \'Cc \'' => ['smtp', 'Cc', false, false, 'Cc ', [['Cc : custom@co.example', 'To: t@x.com']], [['Cc : custom@co.example', 'To: t@x.com']]],
            'smtp, Cc, library does not, one message, custom \' Cc\'' => ['smtp', 'Cc', false, false, ' Cc', [[' Cc: custom@co.example', 'To: t@x.com']], [[' Cc: custom@co.example', 'To: t@x.com']]],
            'smtp, Cc, library does not, BCC batches, custom \'Cc\'' => ['smtp', 'Cc', false, true, 'Cc', [['Cc: custom@co.example', 'To: t@x.com'], ['Cc: custom@co.example']], [['Cc: custom@co.example', 'To: t@x.com'], ['Cc: custom@co.example']]],
            'smtp, Cc, library does not, BCC batches, custom \'cc\'' => ['smtp', 'Cc', false, true, 'cc', [['To: t@x.com', 'cc: custom@co.example'], ['cc: custom@co.example']], [['To: t@x.com', 'cc: custom@co.example'], ['cc: custom@co.example']]],
            'smtp, Cc, library does not, BCC batches, custom \'CC\'' => ['smtp', 'Cc', false, true, 'CC', [['CC: custom@co.example', 'To: t@x.com'], ['CC: custom@co.example']], [['CC: custom@co.example', 'To: t@x.com'], ['CC: custom@co.example']]],
            'smtp, Cc, library does not, BCC batches, custom \'cC\'' => ['smtp', 'Cc', false, true, 'cC', [['To: t@x.com', 'cC: custom@co.example'], ['cC: custom@co.example']], [['To: t@x.com', 'cC: custom@co.example'], ['cC: custom@co.example']]],
            'smtp, Cc, library does not, BCC batches, custom \'Cc \'' => ['smtp', 'Cc', false, true, 'Cc ', [['Cc : custom@co.example', 'To: t@x.com'], ['Cc : custom@co.example']], [['Cc : custom@co.example', 'To: t@x.com'], ['Cc : custom@co.example']]],
            'smtp, Cc, library does not, BCC batches, custom \' Cc\'' => ['smtp', 'Cc', false, true, ' Cc', [[' Cc: custom@co.example', 'To: t@x.com'], [' Cc: custom@co.example']], [[' Cc: custom@co.example', 'To: t@x.com'], [' Cc: custom@co.example']]],
            'smtp, Bcc, library writes it, one message, custom \'Bcc\'' => ['smtp', 'Bcc', true, false, 'Bcc', [['Bcc: custom@co.example', 'To: t@x.com']], [['Bcc: custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library writes it, one message, custom \'bcc\'' => ['smtp', 'Bcc', true, false, 'bcc', [['To: t@x.com', 'bcc: custom@co.example']], [['To: t@x.com', 'bcc: custom@co.example']]],
            'smtp, Bcc, library writes it, one message, custom \'BCC\'' => ['smtp', 'Bcc', true, false, 'BCC', [['BCC: custom@co.example', 'To: t@x.com']], [['BCC: custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library writes it, one message, custom \'bCC\'' => ['smtp', 'Bcc', true, false, 'bCC', [['To: t@x.com', 'bCC: custom@co.example']], [['To: t@x.com', 'bCC: custom@co.example']]],
            'smtp, Bcc, library writes it, one message, custom \'Bcc \'' => ['smtp', 'Bcc', true, false, 'Bcc ', [['Bcc : custom@co.example', 'To: t@x.com']], [['Bcc : custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library writes it, one message, custom \' Bcc\'' => ['smtp', 'Bcc', true, false, ' Bcc', [[' Bcc: custom@co.example', 'To: t@x.com']], [[' Bcc: custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library writes it, BCC batches, custom \'Bcc\'' => ['smtp', 'Bcc', true, true, 'Bcc', [['Bcc: custom@co.example', 'To: t@x.com'], ['Bcc: custom@co.example']], [['Bcc: custom@co.example', 'To: t@x.com'], ['Bcc: custom@co.example']]],
            'smtp, Bcc, library writes it, BCC batches, custom \'bcc\'' => ['smtp', 'Bcc', true, true, 'bcc', [['To: t@x.com', 'bcc: custom@co.example'], ['bcc: custom@co.example']], [['To: t@x.com', 'bcc: custom@co.example'], ['bcc: custom@co.example']]],
            'smtp, Bcc, library writes it, BCC batches, custom \'BCC\'' => ['smtp', 'Bcc', true, true, 'BCC', [['BCC: custom@co.example', 'To: t@x.com'], ['BCC: custom@co.example']], [['BCC: custom@co.example', 'To: t@x.com'], ['BCC: custom@co.example']]],
            'smtp, Bcc, library writes it, BCC batches, custom \'bCC\'' => ['smtp', 'Bcc', true, true, 'bCC', [['To: t@x.com', 'bCC: custom@co.example'], ['bCC: custom@co.example']], [['To: t@x.com', 'bCC: custom@co.example'], ['bCC: custom@co.example']]],
            'smtp, Bcc, library writes it, BCC batches, custom \'Bcc \'' => ['smtp', 'Bcc', true, true, 'Bcc ', [['Bcc : custom@co.example', 'To: t@x.com'], ['Bcc : custom@co.example']], [['Bcc : custom@co.example', 'To: t@x.com'], ['Bcc : custom@co.example']]],
            'smtp, Bcc, library writes it, BCC batches, custom \' Bcc\'' => ['smtp', 'Bcc', true, true, ' Bcc', [[' Bcc: custom@co.example', 'To: t@x.com'], [' Bcc: custom@co.example']], [[' Bcc: custom@co.example', 'To: t@x.com'], [' Bcc: custom@co.example']]],
            'smtp, Bcc, library does not, one message, custom \'Bcc\'' => ['smtp', 'Bcc', false, false, 'Bcc', [['Bcc: custom@co.example', 'To: t@x.com']], [['Bcc: custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library does not, one message, custom \'bcc\'' => ['smtp', 'Bcc', false, false, 'bcc', [['To: t@x.com', 'bcc: custom@co.example']], [['To: t@x.com', 'bcc: custom@co.example']]],
            'smtp, Bcc, library does not, one message, custom \'BCC\'' => ['smtp', 'Bcc', false, false, 'BCC', [['BCC: custom@co.example', 'To: t@x.com']], [['BCC: custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library does not, one message, custom \'bCC\'' => ['smtp', 'Bcc', false, false, 'bCC', [['To: t@x.com', 'bCC: custom@co.example']], [['To: t@x.com', 'bCC: custom@co.example']]],
            'smtp, Bcc, library does not, one message, custom \'Bcc \'' => ['smtp', 'Bcc', false, false, 'Bcc ', [['Bcc : custom@co.example', 'To: t@x.com']], [['Bcc : custom@co.example', 'To: t@x.com']]],
            'smtp, Bcc, library does not, one message, custom \' Bcc\'' => ['smtp', 'Bcc', false, false, ' Bcc', [[' Bcc: custom@co.example', 'To: t@x.com']], [[' Bcc: custom@co.example', 'To: t@x.com']]],
        ];
    }

    /**
     * @dataProvider matrix
     *
     * @param list<list<string>> $expected     Header lines per message, now.
     * @param list<list<string>> $expectedBase Header lines per message, 3.0.0.
     */
    public function testCustomRecipientHeaders(
        string $transport,
        string $header,
        bool $libraryWrites,
        bool $batch,
        string $customName,
        array $expected,
        array $expectedBase,
    ): void {
        $messages = [];
        $client = null;
        $mailer = Mailer::newInstance(['newline' => "\n"]);

        if ($transport === 'mail') {
            $mailer->setTransport(new MailTransport(
                new EmailValidator(),
                static function (string $to, string $subject, string $message, string $headers) use (&$messages): bool {
                    $messages[] = $headers;

                    return true;
                },
            ));
        } elseif ($transport === 'sendmail') {
            $mailer->setTransport(new SendmailTransport(
                '/usr/sbin/sendmail',
                new EmailValidator(),
                static function (string $command, string $message) use (&$messages): int {
                    $messages[] = $message;

                    return 0;
                },
            ));
        } else {
            $client = new RespondingSocketClient();
            $mailer->setTransport(
                new SmtpTransport($client, 'mx.example.com', 25, 5, '', false, '', '', false, false, true, 'localhost', "\n"),
            );
        }

        $mailer->setFrom('s@x.com')->setSubject('S')->setMessage('B');
        if ($header === 'To') {
            if ($libraryWrites) {
                $mailer->setTo('t@x.com');
            } elseif (!$batch) {
                $mailer->setBCC('b@x.com');
            }
        } else {
            $mailer->setTo('t@x.com');
            if ($header === 'Cc' && $libraryWrites) {
                $mailer->setCC('c@x.com');
            }
            if ($header === 'Bcc' && $libraryWrites && !$batch) {
                $mailer->setBCC('b@x.com');
            }
        }
        if ($batch) {
            $mailer->setBCC(['b1@x.com', 'b2@x.com', 'b3@x.com'], 2);
        }
        $mailer->setHeader($customName, 'custom@co.example');
        $mailer->send();

        if ($client !== null) {
            $messages = $client->conversations();
        }

        $actual = array_map(static function (string $message): array {
            $lines = array_map('rtrim', array_values(array_filter(
                explode("\n", $message),
                static fn(string $line): bool => preg_match('/^\s*(to|cc|bcc)\s*:/i', $line) === 1,
            )));
            sort($lines);

            return $lines;
        }, $messages);

        self::assertSame($expected, $actual);
    }

    /**
     * Compares the two recorded columns (it does not run the mailer): `To` rows
     * must be identical to 3.0.0, and for `Cc` and `Bcc` the only line that may
     * be missing is the custom header's own, and only where the library writes
     * that header.
     *
     * @dataProvider matrix
     *
     * @param list<list<string>> $expected
     * @param list<list<string>> $expectedBase
     */
    public function testTheOutputDiffersFromThe300OutputOnlyWhereIntended(
        string $transport,
        string $header,
        bool $libraryWrites,
        bool $batch,
        string $customName,
        array $expected,
        array $expectedBase,
    ): void {
        if ($header === 'To') {
            self::assertSame($expectedBase, $expected, 'A custom To must behave exactly as in 3.0.0.');

            return;
        }

        self::assertCount(count($expectedBase), $expected);
        foreach ($expectedBase as $index => $baseLines) {
            self::assertSame(
                [],
                array_values(array_diff($expected[$index], $baseLines)),
                'No line may appear that 3.0.0 did not write.',
            );
            foreach (array_diff($baseLines, $expected[$index]) as $removed) {
                self::assertStringEndsWith(': custom@co.example', $removed);
                self::assertTrue($libraryWrites, 'A custom header the library does not write must not be removed.');
            }
        }
    }
}
