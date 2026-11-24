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

use function array_filter;
use function array_values;
use function explode;
use function preg_match;

/**
 * `setHeader('Cc'|'Bcc'|'To'|'Subject')` keeps working when the library does
 * not write that header itself, and loses to the library's own header when it
 * does, whatever the case of the name. The "kept" cases below produce the same
 * lines as 3.0.0; the "wins" cases are the intended difference (one header, not
 * two).
 */
final class CustomRecipientHeadersTest extends TestCase
{
    /**
     * @param callable(Mailer): mixed $setup
     */
    private function viaSendmail(callable $setup): string
    {
        $message = '';
        $mailer = Mailer::newInstance(['newline' => "\n"])->setTransport(new SendmailTransport(
            '/usr/sbin/sendmail',
            new EmailValidator(),
            static function (string $command, string $raw) use (&$message): int {
                $message = $raw;

                return 0;
            },
        ));
        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body');
        $setup($mailer);
        $mailer->send();

        return $message;
    }

    /**
     * @param callable(Mailer): mixed $setup
     */
    private function viaSmtp(callable $setup, int $recipients): string
    {
        $replies = ["220 hi\r\n", "250 ehlo\r\n", "250 from\r\n"];
        for ($i = 0; $i < $recipients; ++$i) {
            $replies[] = "250 rcpt\r\n";
        }
        $replies = [...$replies, "354 data\r\n", "250 queued\r\n", "221 bye\r\n"];
        $client = new FakeSocketClient($replies);

        $mailer = Mailer::newInstance(['newline' => "\n"])->setTransport(new SmtpTransport(
            $client,
            'mx.example.com',
            25,
            5,
            '',
            false,
            '',
            '',
            false,
            false,
            true,
            'localhost',
            "\n",
        ));
        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body');
        $setup($mailer);
        $mailer->send();

        return $client->conversation();
    }

    /**
     * @param callable(Mailer): mixed $setup
     */
    private function viaMail(callable $setup): string
    {
        $captured = '';
        $mailer = Mailer::newInstance(['newline' => "\n"])->setTransport(new MailTransport(
            new EmailValidator(),
            static function (string $to, string $subject, string $message, string $headers) use (&$captured): bool {
                $captured = $headers;

                return true;
            },
        ));
        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body');
        $setup($mailer);
        $mailer->send();

        return $captured;
    }

    /**
     * The header lines of `$message` whose name is `$name` (case-insensitive).
     *
     * @return list<string>
     */
    private function lines(string $message, string $name): array
    {
        return array_values(array_filter(
            explode("\n", $message),
            static fn(string $line): bool => preg_match('/^' . $name . ':/i', $line) === 1,
        ));
    }

    public function testSendmailKeepsACustomCcAndBccWhenThereAreNoRecipientsOfThatKind(): void
    {
        $message = $this->viaSendmail(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setHeader('Bcc', 'archive@co.example')->setHeader('Cc', 'c@co.example'),
        );

        // Same lines as 3.0.0.
        self::assertSame(['Bcc: archive@co.example'], $this->lines($message, 'Bcc'));
        self::assertSame(['Cc: c@co.example'], $this->lines($message, 'Cc'));
    }

    public function testSendmailRealBccWinsOverACustomBcc(): void
    {
        $message = $this->viaSendmail(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setBCC('real@example.com')->setHeader('Bcc', 'archive@co.example'),
        );

        // Same single line as 3.0.0.
        self::assertSame(['Bcc: real@example.com'], $this->lines($message, 'Bcc'));
    }

    public function testSendmailRealBccWinsWhateverTheCaseOfTheCustomName(): void
    {
        $message = $this->viaSendmail(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setBCC('real@example.com')->setHeader('bCC', 'archive@co.example'),
        );

        // 3.0.0 wrote both a "bCC:" and a "Bcc:" line.
        self::assertSame(['Bcc: real@example.com'], $this->lines($message, 'Bcc'));
    }

    public function testSendmailRealCcToAndSubjectWinWhateverTheCase(): void
    {
        $message = $this->viaSendmail(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setCC('real@example.com')
                ->setHeader('CC', 'archive@co.example')
                ->setHeader('to', 'other@co.example')
                ->setHeader('SUBJECT', 'evil'),
        );

        self::assertSame(['Cc: real@example.com'], $this->lines($message, 'Cc'));
        self::assertSame(['To: t@example.com'], $this->lines($message, 'To'));
        self::assertCount(1, $this->lines($message, 'Subject'));
        self::assertStringNotContainsString('evil', $message);
    }

    public function testSmtpKeepsACustomCcAndBccTheTransportDoesNotWrite(): void
    {
        $data = $this->viaSmtp(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setHeader('Bcc', 'archive@co.example')->setHeader('Cc', 'c@co.example'),
            1,
        );

        // Same lines as 3.0.0 (SMTP never writes a Bcc header itself).
        self::assertSame(['Bcc: archive@co.example'], $this->lines($data, 'Bcc'));
        self::assertSame(['Cc: c@co.example'], $this->lines($data, 'Cc'));
    }

    public function testSmtpRealCcToAndSubjectWinWhateverTheCase(): void
    {
        $data = $this->viaSmtp(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setCC('real@example.com')
                ->setHeader('cC', 'archive@co.example')
                ->setHeader('tO', 'other@co.example')
                ->setHeader('subject', 'evil'),
            2,
        );

        self::assertSame(['Cc: real@example.com'], $this->lines($data, 'Cc'));
        self::assertSame(['To: t@example.com'], $this->lines($data, 'To'));
        self::assertCount(1, $this->lines($data, 'Subject'));
        self::assertStringNotContainsString('evil', $data);
    }

    public function testMailKeepsACustomCcAndRealBccWins(): void
    {
        $headers = $this->viaMail(
            static fn(Mailer $m) => $m->setTo('t@example.com')->setBCC('real@example.com')
                ->setHeader('bcc', 'archive@co.example')
                ->setHeader('Cc', 'c@co.example'),
        );

        self::assertSame(['Bcc: real@example.com'], $this->lines($headers, 'Bcc'));
        self::assertSame(['Cc: c@co.example'], $this->lines($headers, 'Cc'));
    }

    public function testBccBatchesAfterTheFirstDoNotCarryACustomToWhenTheMessageHasATo(): void
    {
        $messages = [];
        $mailer = Mailer::newInstance(['newline' => "\n"])->setTransport(new SendmailTransport(
            '/usr/sbin/sendmail',
            new EmailValidator(),
            static function (string $command, string $raw) use (&$messages): int {
                $messages[] = $raw;

                return 0;
            },
        ));
        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body')
            ->setTo('t@example.com')
            ->setBCC(['b1@example.com', 'b2@example.com', 'b3@example.com'], 2)
            ->setHeader('To', 'other@co.example')
            ->send();

        self::assertCount(2, $messages);
        self::assertSame(['To: t@example.com'], $this->lines($messages[0], 'To'));
        self::assertSame([], $this->lines($messages[1], 'To'));
    }

    public function testBccOnlyBatchesKeepACustomTo(): void
    {
        $messages = [];
        $mailer = Mailer::newInstance(['newline' => "\n"])->setTransport(new SendmailTransport(
            '/usr/sbin/sendmail',
            new EmailValidator(),
            static function (string $command, string $raw) use (&$messages): int {
                $messages[] = $raw;

                return 0;
            },
        ));
        $mailer->setFrom('s@example.com')->setSubject('Subject')->setMessage('Body')
            ->setBCC(['b1@example.com', 'b2@example.com', 'b3@example.com'], 2)
            ->setHeader('To', 'undisclosed-recipients:;')
            ->send();

        self::assertCount(2, $messages);
        foreach ($messages as $message) {
            self::assertSame(['To: undisclosed-recipients:;'], $this->lines($message, 'To'));
        }
    }
}
