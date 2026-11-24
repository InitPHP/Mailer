<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\FakeSocketClient;
use InitPHP\Mailer\Transport\MailTransport;
use InitPHP\Mailer\Transport\SendmailTransport;
use InitPHP\Mailer\Transport\SmtpTransport;
use InitPHP\Mailer\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

use function array_diff;
use function array_filter;
use function array_map;
use function array_values;
use function explode;
use function preg_match;
use function sort;

/**
 * A custom To, Cc or Bcc header set with setHeader(), across the three
 * transports: the header the library writes itself wins whatever the case of the
 * custom name, and one it does not write is passed through.
 *
 * Each row holds the To/Cc/Bcc header lines of the message as they are now and as
 * 3.0.0 produced them for the same calls (recorded by running that release's
 * source). Where the two differ the difference is intended: a custom header the
 * library also writes is no longer emitted as a second line, and a custom To is
 * kept when the message has no To recipient (3.0.0 lost it on sendmail and SMTP).
 */
final class RecipientHeaderMatrixTest extends TestCase
{
    /**
     * @return array<string, array{string, string, bool, string, list<string>, list<string>}>
     */
    public static function matrix(): array
    {
        return [
            'mail, custom To, library writes it' => ['mail', 'To', true, 'To', [], ['To: custom@co.example']],
            'mail, custom to, library writes it' => ['mail', 'To', true, 'to', [], ['to: custom@co.example']],
            'mail, custom To, library does not' => ['mail', 'To', false, 'To', ['Bcc: b@x.com', 'To: custom@co.example'], ['Bcc: b@x.com', 'To: custom@co.example']],
            'mail, custom to, library does not' => ['mail', 'To', false, 'to', ['Bcc: b@x.com', 'to: custom@co.example'], ['Bcc: b@x.com', 'to: custom@co.example']],
            'mail, custom Cc, library writes it' => ['mail', 'Cc', true, 'Cc', ['Cc: c@x.com'], ['Cc: c@x.com']],
            'mail, custom cc, library writes it' => ['mail', 'Cc', true, 'cc', ['Cc: c@x.com'], ['Cc: c@x.com', 'cc: custom@co.example']],
            'mail, custom Cc, library does not' => ['mail', 'Cc', false, 'Cc', ['Cc: custom@co.example'], ['Cc: custom@co.example']],
            'mail, custom cc, library does not' => ['mail', 'Cc', false, 'cc', ['cc: custom@co.example'], ['cc: custom@co.example']],
            'mail, custom Bcc, library writes it' => ['mail', 'Bcc', true, 'Bcc', ['Bcc: b@x.com'], ['Bcc: b@x.com']],
            'mail, custom bcc, library writes it' => ['mail', 'Bcc', true, 'bcc', ['Bcc: b@x.com'], ['Bcc: b@x.com', 'bcc: custom@co.example']],
            'mail, custom Bcc, library does not' => ['mail', 'Bcc', false, 'Bcc', ['Bcc: custom@co.example'], ['Bcc: custom@co.example']],
            'mail, custom bcc, library does not' => ['mail', 'Bcc', false, 'bcc', ['bcc: custom@co.example'], ['bcc: custom@co.example']],
            'sendmail, custom To, library writes it' => ['sendmail', 'To', true, 'To', ['To: t@x.com'], ['To: t@x.com']],
            'sendmail, custom to, library writes it' => ['sendmail', 'To', true, 'to', ['To: t@x.com'], ['To: t@x.com', 'to: custom@co.example']],
            'sendmail, custom To, library does not' => ['sendmail', 'To', false, 'To', ['Bcc: b@x.com', 'To: custom@co.example'], ['Bcc: b@x.com']],
            'sendmail, custom to, library does not' => ['sendmail', 'To', false, 'to', ['Bcc: b@x.com', 'to: custom@co.example'], ['Bcc: b@x.com', 'to: custom@co.example']],
            'sendmail, custom Cc, library writes it' => ['sendmail', 'Cc', true, 'Cc', ['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com', 'To: t@x.com']],
            'sendmail, custom cc, library writes it' => ['sendmail', 'Cc', true, 'cc', ['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example']],
            'sendmail, custom Cc, library does not' => ['sendmail', 'Cc', false, 'Cc', ['Cc: custom@co.example', 'To: t@x.com'], ['Cc: custom@co.example', 'To: t@x.com']],
            'sendmail, custom cc, library does not' => ['sendmail', 'Cc', false, 'cc', ['To: t@x.com', 'cc: custom@co.example'], ['To: t@x.com', 'cc: custom@co.example']],
            'sendmail, custom Bcc, library writes it' => ['sendmail', 'Bcc', true, 'Bcc', ['Bcc: b@x.com', 'To: t@x.com'], ['Bcc: b@x.com', 'To: t@x.com']],
            'sendmail, custom bcc, library writes it' => ['sendmail', 'Bcc', true, 'bcc', ['Bcc: b@x.com', 'To: t@x.com'], ['Bcc: b@x.com', 'To: t@x.com', 'bcc: custom@co.example']],
            'sendmail, custom Bcc, library does not' => ['sendmail', 'Bcc', false, 'Bcc', ['Bcc: custom@co.example', 'To: t@x.com'], ['Bcc: custom@co.example', 'To: t@x.com']],
            'sendmail, custom bcc, library does not' => ['sendmail', 'Bcc', false, 'bcc', ['To: t@x.com', 'bcc: custom@co.example'], ['To: t@x.com', 'bcc: custom@co.example']],
            'smtp, custom To, library writes it' => ['smtp', 'To', true, 'To', ['To: t@x.com'], ['To: t@x.com']],
            'smtp, custom to, library writes it' => ['smtp', 'To', true, 'to', ['To: t@x.com'], ['To: t@x.com', 'to: custom@co.example']],
            'smtp, custom To, library does not' => ['smtp', 'To', false, 'To', ['To: custom@co.example'], []],
            'smtp, custom to, library does not' => ['smtp', 'To', false, 'to', ['to: custom@co.example'], ['to: custom@co.example']],
            'smtp, custom Cc, library writes it' => ['smtp', 'Cc', true, 'Cc', ['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com', 'To: t@x.com']],
            'smtp, custom cc, library writes it' => ['smtp', 'Cc', true, 'cc', ['Cc: c@x.com', 'To: t@x.com'], ['Cc: c@x.com', 'To: t@x.com', 'cc: custom@co.example']],
            'smtp, custom Cc, library does not' => ['smtp', 'Cc', false, 'Cc', ['Cc: custom@co.example', 'To: t@x.com'], ['Cc: custom@co.example', 'To: t@x.com']],
            'smtp, custom cc, library does not' => ['smtp', 'Cc', false, 'cc', ['To: t@x.com', 'cc: custom@co.example'], ['To: t@x.com', 'cc: custom@co.example']],
            'smtp, custom Bcc, library writes it' => ['smtp', 'Bcc', true, 'Bcc', ['Bcc: custom@co.example', 'To: t@x.com'], ['Bcc: custom@co.example', 'To: t@x.com']],
            'smtp, custom bcc, library writes it' => ['smtp', 'Bcc', true, 'bcc', ['To: t@x.com', 'bcc: custom@co.example'], ['To: t@x.com', 'bcc: custom@co.example']],
            'smtp, custom Bcc, library does not' => ['smtp', 'Bcc', false, 'Bcc', ['Bcc: custom@co.example', 'To: t@x.com'], ['Bcc: custom@co.example', 'To: t@x.com']],
            'smtp, custom bcc, library does not' => ['smtp', 'Bcc', false, 'bcc', ['To: t@x.com', 'bcc: custom@co.example'], ['To: t@x.com', 'bcc: custom@co.example']],
        ];
    }

    /**
     * @dataProvider matrix
     *
     * @param list<string> $expected     The header lines now.
     * @param list<string> $expectedBase The header lines 3.0.0 produced.
     */
    public function testCustomRecipientHeaders(
        string $transport,
        string $header,
        bool $libraryWrites,
        string $customName,
        array $expected,
        array $expectedBase,
    ): void {
        $captured = '';
        $client = null;
        $mailer = Mailer::newInstance(['newline' => "\n"]);

        if ($transport === 'mail') {
            $mailer->setTransport(new MailTransport(
                new EmailValidator(),
                static function (string $to, string $subject, string $message, string $headers) use (&$captured): bool {
                    $captured = $headers;

                    return true;
                },
            ));
        } elseif ($transport === 'sendmail') {
            $mailer->setTransport(new SendmailTransport(
                '/usr/sbin/sendmail',
                new EmailValidator(),
                static function (string $command, string $message) use (&$captured): int {
                    $captured = $message;

                    return 0;
                },
            ));
        } else {
            $recipients = $header === 'To' || !$libraryWrites ? 1 : 2;
            $replies = ["220 hi\r\n", "250 ehlo\r\n", "250 from\r\n"];
            for ($i = 0; $i < $recipients; ++$i) {
                $replies[] = "250 rcpt\r\n";
            }
            $client = new FakeSocketClient([...$replies, "354 data\r\n", "250 queued\r\n", "221 bye\r\n"]);
            $mailer->setTransport(
                new SmtpTransport($client, 'mx.example.com', 25, 5, '', false, '', '', false, false, true, 'localhost', "\n"),
            );
        }

        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body');
        if ($header === 'To') {
            $libraryWrites ? $mailer->setTo('t@x.com') : $mailer->setBCC('b@x.com');
        } else {
            $mailer->setTo('t@x.com');
            if ($libraryWrites) {
                $header === 'Cc' ? $mailer->setCC('c@x.com') : $mailer->setBCC('b@x.com');
            }
        }
        $mailer->setHeader($customName, 'custom@co.example');
        $mailer->send();

        if ($client !== null) {
            $captured = $client->conversation();
        }

        $lines = array_map('rtrim', array_values(array_filter(
            explode("\n", $captured),
            static fn(string $line): bool => preg_match('/^(to|cc|bcc):/i', $line) === 1,
        )));
        sort($lines);

        self::assertSame($expected, $lines);
    }

    /**
     * Compares the two recorded columns (it does not run the mailer) and allows
     * the difference from 3.0.0 to be only of the two intended kinds, so a changed
     * expectation that drops or adds anything else shows up here.
     *
     * @dataProvider matrix
     *
     * @param list<string> $expected
     * @param list<string> $expectedBase
     */
    public function testTheOutputDiffersFromThe300OutputOnlyWhereIntended(
        string $transport,
        string $header,
        bool $libraryWrites,
        string $customName,
        array $expected,
        array $expectedBase,
    ): void {
        $removed = array_diff($expectedBase, $expected);
        $added = array_diff($expected, $expectedBase);
        $customLine = $customName . ': custom@co.example';

        // Only the custom header's own line may disappear, and only when the
        // library writes that header (it used to come out as a duplicate)...
        $mayRemove = $libraryWrites ? [$customLine] : [];
        // ...and the only line that may appear is a custom To that 3.0.0 lost on
        // sendmail and SMTP although the library wrote no To.
        $mayAdd = $header === 'To' && !$libraryWrites && $transport !== 'mail' ? [$customLine] : [];

        self::assertSame([], array_values(array_diff($removed, $mayRemove)), 'Unexpected line removed.');
        self::assertSame([], array_values(array_diff($added, $mayAdd)), 'Unexpected line added.');
    }
}
