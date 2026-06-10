<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests;

use InitPHP\Mailer\MailerConfig;
use PHPUnit\Framework\TestCase;

final class MailerConfigTest extends TestCase
{
    public function testProvidesSensibleDefaults(): void
    {
        $config = new MailerConfig();

        $this->assertSame('mail', $config->protocol);
        $this->assertSame('text', $config->mailType);
        $this->assertSame('UTF-8', $config->charset);
        $this->assertSame(25, $config->smtpPort);
        $this->assertSame(3, $config->priority);
        $this->assertTrue($config->validate);
        $this->assertFalse($config->smtpAuth);
    }

    /**
     * Regression: in 1.x the constructor guard was inverted and the supplied
     * configuration was silently ignored.
     */
    public function testAppliesSuppliedConfiguration(): void
    {
        $config = new MailerConfig([
            'protocol' => 'smtp',
            'SMTPHost' => 'smtp.example.com',
            'SMTPUser' => 'user@example.com',
            'SMTPPass' => 'secret',
            'SMTPPort' => 587,
        ]);

        $this->assertSame('smtp', $config->protocol);
        $this->assertSame('smtp.example.com', $config->smtpHost);
        $this->assertSame('user@example.com', $config->smtpUser);
        $this->assertSame('secret', $config->smtpPass);
        $this->assertSame(587, $config->smtpPort);
    }

    public function testDerivesSmtpAuthFromCredentials(): void
    {
        $this->assertTrue((new MailerConfig(['SMTPUser' => 'u', 'SMTPPass' => 'p']))->smtpAuth);
        $this->assertFalse((new MailerConfig(['SMTPUser' => 'u']))->smtpAuth);
    }

    public function testNormalisesValues(): void
    {
        $config = new MailerConfig([
            'charset'    => 'utf-8',
            'protocol'   => 'SMTP',
            'mailType'   => 'HTML',
            'priority'   => 9,
            'SMTPCrypto' => 'TLS',
        ]);

        $this->assertSame('UTF-8', $config->charset);
        $this->assertSame('smtp', $config->protocol);
        $this->assertSame('html', $config->mailType);
        $this->assertSame(3, $config->priority, 'Out-of-range priority falls back to 3.');
        $this->assertSame('tls', $config->smtpCrypto);
    }

    public function testRejectsUnknownCrypto(): void
    {
        $this->assertSame('', (new MailerConfig(['SMTPCrypto' => 'rot13']))->smtpCrypto);
    }

    public function testIgnoresInvalidNewline(): void
    {
        $this->assertSame(\PHP_EOL, (new MailerConfig(['newline' => 'xx']))->newline);
        $this->assertSame("\r\n", (new MailerConfig(['newline' => "\r\n"]))->newline);
    }
}
