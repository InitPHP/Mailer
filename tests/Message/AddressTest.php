<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Message;

use InitPHP\Mailer\Message\Address;
use PHPUnit\Framework\TestCase;

final class AddressTest extends TestCase
{
    public function testParsesABareAddress(): void
    {
        $address = Address::parse('user@example.com');

        $this->assertSame('user@example.com', $address->getEmail());
        $this->assertSame('', $address->getName());
        $this->assertFalse($address->hasName());
    }

    public function testParsesAnAngleBracketedAddress(): void
    {
        $address = Address::parse('<user@example.com>');

        $this->assertSame('user@example.com', $address->getEmail());
    }

    public function testParsesANameAndAddress(): void
    {
        $address = Address::parse('John Doe <user@example.com>');

        $this->assertSame('user@example.com', $address->getEmail());
        $this->assertSame('John Doe', $address->getName());
        $this->assertTrue($address->hasName());
    }

    public function testStripsQuotesFromAnEmbeddedName(): void
    {
        $address = Address::parse('"Doe, John" <user@example.com>');

        $this->assertSame('user@example.com', $address->getEmail());
        $this->assertSame('Doe, John', $address->getName());
    }

    public function testExplicitNameWinsOverEmbeddedName(): void
    {
        $address = Address::parse('Embedded <user@example.com>', 'Explicit');

        $this->assertSame('user@example.com', $address->getEmail());
        $this->assertSame('Explicit', $address->getName());
    }
}
