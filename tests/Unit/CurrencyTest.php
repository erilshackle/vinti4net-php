<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erilshk\Sisp\Currency;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use PHPUnit\Framework\TestCase;

final class CurrencyTest extends TestCase
{
    public function testConvertsNamesAndPreservesNumericCodes(): void
    {
        foreach (['CVE' => '132', 'USD' => '840', 'EUR' => '978', 'BRL' => '986', 'GBP' => '826', 'JPY' => '392'] as $name => $code) {
            self::assertSame($code, Currency::toNumeric(' ' . strtolower($name) . ' '));
            self::assertSame($code, Currency::toNumeric($code));
        }
        self::assertSame('008', Currency::toNumeric('008'));
    }

    public function testRejectsUnknownNames(): void
    {
        $this->expectException(Vinti4Exception::class);
        Currency::toNumeric('GPD');
    }
}
