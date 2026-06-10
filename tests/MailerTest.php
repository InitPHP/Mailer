<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\Exception\AttachmentException;
use InitPHP\Mailer\Exception\ConfigurationException;
use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    private RecordingTransport $transport;

    /**
     * @param array<string, mixed> $config
     */
    private function mailer(array $config = []): Mailer
    {
        $this->transport = new RecordingTransport();

        return Mailer::newInstance($config)->setTransport($this->transport);
    }

    public function testUsesConfiguredSenderWhenSetFromIsNotCalled(): void
    {
        $this->mailer(['fromEmail' => 'cfg@example.com', 'fromName' => 'Config'])
            ->setTo('rcpt@example.com')
            ->setMessage('Body')
            ->send();

        $this->assertSame('cfg@example.com', $this->transport->last()->from->getEmail());
    }

    public function testXSenderUsesTheBareAddress(): void
    {
        $this->mailer()
            ->setFrom('sender@example.com', 'Display Name')
            ->setTo('rcpt@example.com')
            ->send();

        $this->assertSame('sender@example.com', $this->transport->last()->headers['X-Sender']);
    }

    public function testAsciiSenderNameIsQuotedNotEncoded(): void
    {
        $this->mailer()->setFrom('sender@example.com', 'John Doe')->setTo('rcpt@example.com')->send();

        $this->assertSame('"John Doe" <sender@example.com>', $this->transport->last()->headers['From']);
    }

    public function testNonAsciiSenderNameIsQEncoded(): void
    {
        $this->mailer()->setFrom('sender@example.com', 'Münir')->setTo('rcpt@example.com')->send();

        $from = $this->transport->last()->headers['From'];
        $this->assertStringContainsString('=?UTF-8?Q?', $from);
        $this->assertStringContainsString('<sender@example.com>', $from);
    }

    public function testReplyToDefaultsToTheSenderAddress(): void
    {
        $this->mailer()->setFrom('sender@example.com', 'John Doe')->setTo('rcpt@example.com')->send();

        $this->assertStringEndsWith('<sender@example.com>', $this->transport->last()->headers['Reply-To']);
    }

    public function testExplicitReplyTo(): void
    {
        $this->mailer()
            ->setFrom('sender@example.com')
            ->setReplyTo('reply@example.com')
            ->setTo('rcpt@example.com')
            ->send();

        $this->assertSame('<reply@example.com>', $this->transport->last()->headers['Reply-To']);
    }

    public function testCcIsAddedToHeaders(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')->setCC('c@example.com')->send();

        $this->assertSame('c@example.com', $this->transport->last()->headers['Cc']);
    }

    public function testSubjectIsQEncoded(): void
    {
        $this->mailer()->setFrom('s@example.com')->setTo('a@example.com')->setSubject('Münir')->send();

        $this->assertStringContainsString('=?UTF-8?Q?', $this->transport->last()->subject);
    }

    public function testHtmlBodyIsNotTruncated(): void
    {
        $this->mailer(['mailType' => 'html'])
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setMessage('<p>Hello world, this is the body.</p>')
            ->send();

        $this->assertGreaterThan(20, \strlen($this->transport->last()->body));
    }

    public function testInlineAttachmentProducesRelatedHeader(): void
    {
        $mailer = $this->mailer(['mailType' => 'html'])
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setMessage('<p><img src="cid:x"></p>')
            ->attachContent('IMGDATA', 'logo.png', 'inline', 'image/png');
        $mailer->setAttachmentCID('logo.png');
        $mailer->send();

        $this->assertStringContainsString('multipart/related', $this->transport->last()->contentHeaders);
    }

    public function testSetToRejectsInvalidAddress(): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->mailer()->setTo('not-an-email');
    }

    public function testValidationCanBeDisabled(): void
    {
        $this->mailer(['validate' => false])->setFrom('s@example.com')->setTo('not-an-email')->send();

        $this->assertSame(['not-an-email'], $this->transport->last()->to);
    }

    public function testThrowsWhenNoRecipient(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->mailer()->setFrom('s@example.com')->setMessage('Body')->send();
    }

    public function testThrowsWhenNoSender(): void
    {
        $this->expectException(ConfigurationException::class);

        $this->mailer()->setTo('a@example.com')->send();
    }

    public function testAttachingAMissingFileThrows(): void
    {
        $this->expectException(AttachmentException::class);

        $this->mailer()->attach('/no/such/file/at/all.txt');
    }

    public function testSetAttachmentCidThrowsWhenNotFound(): void
    {
        $this->expectException(AttachmentException::class);

        $this->mailer()->setAttachmentCID('missing.png');
    }

    public function testArchiveIsNullBeforeSending(): void
    {
        $this->assertNull($this->mailer()->getArchive());
    }

    public function testArchiveCapturesTheMessageWithoutCredentials(): void
    {
        $mailer = $this->mailer(['SMTPPass' => 'topsecret']);
        $mailer->setFrom('s@example.com')->setTo('a@example.com')->setSubject('Hi')->send();
        $archive = $mailer->getArchive();

        $this->assertIsArray($archive);
        $this->assertSame('Hi', $archive['subject']);
        $this->assertArrayNotHasKey('SMTPPass', $archive);
        $this->assertArrayNotHasKey('smtpPass', $archive);
    }

    public function testAutoClearResetsRecipients(): void
    {
        $mailer = $this->mailer()->setFrom('s@example.com')->setTo('a@example.com');
        $mailer->send();

        $this->expectException(ConfigurationException::class);
        $mailer->send();
    }

    public function testBccBatchingSplitsAcrossMessages(): void
    {
        $mailer = $this->mailer()
            ->setFrom('s@example.com')
            ->setTo('to@example.com')
            ->setBCC('b1@example.com, b2@example.com, b3@example.com, b4@example.com, b5@example.com', 2);
        $mailer->send();

        $this->assertCount(3, $this->transport->sent);
        $this->assertSame(['b1@example.com', 'b2@example.com'], $this->transport->sent[0]->bcc);
        $this->assertSame(['to@example.com'], $this->transport->sent[0]->to);
        // To/Cc only ride along with the first batch.
        $this->assertSame([], $this->transport->sent[1]->to);
        $this->assertSame(['b5@example.com'], $this->transport->sent[2]->bcc);
    }

    public function testCustomHeaderIsIncludedAndSanitised(): void
    {
        $this->mailer()
            ->setFrom('s@example.com')
            ->setTo('a@example.com')
            ->setHeader('X-Custom', "value\r\nBcc: injected@example.com")
            ->send();

        $this->assertSame('valueBcc: injected@example.com', $this->transport->last()->headers['X-Custom']);
    }

    public function testIsValidEmail(): void
    {
        $mailer = $this->mailer();

        $this->assertTrue($mailer->isValidEmail('a@example.com'));
        $this->assertFalse($mailer->isValidEmail('nope'));
        $this->assertTrue($mailer->validateEmail(['a@example.com', 'b@example.com']));
        $this->assertFalse($mailer->validateEmail(['a@example.com', 'bad']));
    }
}
