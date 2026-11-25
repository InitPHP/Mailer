<?php

declare(strict_types=1);

namespace InitPHP\Mailer\Tests\Message;

use InitPHP\Mailer\Exception\InvalidAddressException;
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

    /**
     * @dataProvider unsafeParts
     */
    public function testRejectsALineBreakOrNulInTheAddress(string $email, string $name): void
    {
        $this->expectException(InvalidAddressException::class);

        new Address($email, $name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function unsafeParts(): array
    {
        return [
            'CRLF in the address' => ["a@example.com\r\nBcc: x@example.test", ''],
            'LF in the address'   => ["a@example.com\nBcc: x@example.test", ''],
            'CR in the address'   => ["a@example.com\rBcc: x@example.test", ''],
            'NUL in the address'  => ["a@example.com\0", ''],
        ];
    }

    public function testParseRejectsALineBreakInsideTheAddressPart(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::parse("Name <a@example.com\r\nBcc: x@example.test>");
    }

    public function testParseStillTrimsASurroundingLineBreak(): void
    {
        $address = Address::parse("  a@example.com\r\n");

        $this->assertSame('a@example.com', $address->getEmail());
    }

    public function testAnAddressIsNotRejectedForALineBreakInTheNameItCarries(): void
    {
        // The regex lets `.` match a CR, so the "name" part of this raw value
        // holds the line break; the address is the clean part inside <...>.
        $address = Address::parse("Evil\rBcc: x@example.test <a@example.com>");

        $this->assertSame('a@example.com', $address->getEmail());
        $this->assertSame("Evil\rBcc: x@example.test", $address->getName());
    }

    public function testADisplayNameWithALineBreakOrNulIsKeptNotRejected(): void
    {
        $address = new Address('a@example.com', "Na\r\nme\0");

        $this->assertSame("Na\r\nme\0", $address->getName());
    }

    /**
     * @dataProvider angleBrackets
     */
    public function testRejectsAnAngleBracketInsideTheAddress(string $email): void
    {
        $this->expectException(InvalidAddressException::class);

        new Address($email);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function angleBrackets(): array
    {
        return [
            'closing'  => ['v@x.com> NOTIFY=NEVER'],
            'opening'  => ['v@x.com<y'],
        ];
    }

    public function testParseRejectsATrailingSmtpParameter(): void
    {
        $this->expectException(InvalidAddressException::class);

        Address::parse('Name <v@x.com> SIZE=1>');
    }
}
