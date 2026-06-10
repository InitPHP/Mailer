<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Facade;

use InitPHP\Mailer\Facade\Mailer as Facade;
use InitPHP\Mailer\Mailer;
use InitPHP\Mailer\Tests\Support\RecordingTransport;
use PHPUnit\Framework\TestCase;

final class MailerFacadeTest extends TestCase
{
    protected function tearDown(): void
    {
        Facade::setInstance(null);
    }

    public function testForwardsStaticCallsToTheSharedInstance(): void
    {
        $transport = new RecordingTransport();
        Facade::setInstance(Mailer::newInstance()->setTransport($transport));

        Facade::setFrom('sender@example.com')
            ->setTo('rcpt@example.com')
            ->setSubject('Hello')
            ->send();

        $this->assertCount(1, $transport->sent);
        $this->assertSame('sender@example.com', $transport->last()->from->getEmail());
    }

    public function testExposesQueryMethodsStatically(): void
    {
        $this->assertTrue(Facade::isValidEmail('a@example.com'));
        $this->assertFalse(Facade::isValidEmail('nope'));
    }
}
