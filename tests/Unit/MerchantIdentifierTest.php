<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Unit;

use Eril\Sisp\Exception\InvalidRequestException;
use Eril\Sisp\Vinti4Net;
use PHPUnit\Framework\TestCase;

final class MerchantIdentifierTest extends TestCase
{
    public function testItGeneratesSessionWithExpectedFormat(): void
    {
        $session = Vinti4Net::generateSession();

        self::assertSame(15, strlen($session));
        self::assertMatchesRegularExpression(
            '/^S\d{14}$/',
            $session,
        );
    }

    public function testItGeneratesReferenceWithDefaultPrefix(): void
    {
        $reference = Vinti4Net::generateReference();

        self::assertSame(15, strlen($reference));
        self::assertStringStartsWith('R', $reference);
        self::assertMatchesRegularExpression(
            '/^R[A-F0-9]{14}$/',
            $reference,
        );
    }

    public function testItGeneratesReferenceWithCustomPrefix(): void
    {
        $reference = Vinti4Net::generateReference('FAT-');

        self::assertSame(15, strlen($reference));
        self::assertStringStartsWith('FAT-', $reference);
        self::assertMatchesRegularExpression(
            '/^FAT-[A-F0-9]{11}$/',
            $reference,
        );
    }

    public function testItRejectsPrefixLongerThanSixCharacters(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage(
            'O prefixo da referência deve ter no máximo 6 caracteres.'
        );

        Vinti4Net::generateReference('PREFIXO');
    }

    public function testItRejectsInvalidPrefixCharacters(): void
    {
        $this->expectException(InvalidRequestException::class);

        Vinti4Net::generateReference('FAT_');
    }
}