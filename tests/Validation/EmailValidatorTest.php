<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Validation;

use InitPHP\Mailer\Exception\InvalidAddressException;
use InitPHP\Mailer\Validation\EmailValidator;
use PHPUnit\Framework\TestCase;

final class EmailValidatorTest extends TestCase
{
    private EmailValidator $validator;

    protected function setUp(): void
    {
        $this->validator = new EmailValidator();
    }

    public function testAcceptsValidAddresses(): void
    {
        $this->assertTrue($this->validator->isValid('user@example.com'));
        $this->assertTrue($this->validator->isValid('first.last+tag@sub.example.co.uk'));
    }

    public function testRejectsInvalidAddresses(): void
    {
        $this->assertFalse($this->validator->isValid('not-an-email'));
        $this->assertFalse($this->validator->isValid('a@b@c.com'));
    }

    public function testAssertValidThrowsWithTheOffendingAddress(): void
    {
        $this->expectException(InvalidAddressException::class);
        $this->expectExceptionMessage('"nope" is not a valid e-mail address.');

        $this->validator->assertValid('nope');
    }

    public function testAssertAllValidThrowsOnTheFirstInvalidAddress(): void
    {
        $this->expectException(InvalidAddressException::class);

        $this->validator->assertAllValid(['ok@example.com', 'broken']);
    }

    public function testAssertAllValidPassesForValidList(): void
    {
        $this->validator->assertAllValid(['a@example.com', 'b@example.com']);

        $this->addToAssertionCount(1);
    }

    public function testShellSafeAcceptsPlainAddresses(): void
    {
        $this->assertTrue($this->validator->isShellSafe('user@example.com'));
    }

    public function testShellSafeRejectsAddressesWithMetacharacters(): void
    {
        $this->assertFalse($this->validator->isShellSafe('"a b"@example.com'));
        $this->assertFalse($this->validator->isShellSafe('user@example.com -X/tmp/x'));
    }
}
