<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Unit;

use Eril\Sisp\Billing;
use Eril\Sisp\Exception\InvalidRequestException;
use PHPUnit\Framework\TestCase;

final class BillingTest extends TestCase
{
    public function testItCreatesBillingFromFriendlyFields(): void
    {
        $billing = Billing::from([
            'email' => 'customer@example.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Rua Principal, 1',
            'address2' => 'Apartamento 2',
            'postalCode' => '7600',
            'mobilePhone' => [
                'cc' => '238',
                'subscriber' => '991-12-34',
            ],
            'addrMatch' => true,
            'accountId' => 'USER-123',
        ])->toArray();

        self::assertSame('customer@example.com', $billing['email']);
        self::assertSame('132', $billing['billAddrCountry']);
        self::assertSame('Praia', $billing['billAddrCity']);
        self::assertSame('Rua Principal, 1', $billing['billAddrLine1']);
        self::assertSame('Apartamento 2', $billing['billAddrLine2']);
        self::assertSame('7600', $billing['billAddrPostCode']);
        self::assertSame('Y', $billing['addrMatch']);
        self::assertSame('USER-123', $billing['acctID']);
        self::assertSame([
            'cc' => '238',
            'subscriber' => '9911234',
        ], $billing['mobilePhone']);
    }

    public function testFluentBuilderPreservesAllSupportedData(): void
    {
        $billing = Billing::make()
            ->email('customer@example.com')
            ->country('132')
            ->city('Praia')
            ->address('Rua Principal, 1')
            ->postalCode('7600')
            ->shipCountry('132')
            ->shipCity('Praia')
            ->shipAddress('Rua de Entrega, 2')
            ->shipPostalCode('7601')
            ->mobilePhone('238', '991 12 34')
            ->workPhone('238', '261 12 34')
            ->accountId('USER-123')
            ->accountInfo(['chAccAgeInd' => '05'])
            ->suspicious(false)
            ->addressMatchesShipping(false)
            ->toArray();

        self::assertSame('Rua de Entrega, 2', $billing['shipAddrLine1']);
        self::assertSame('N', $billing['addrMatch']);
        self::assertSame('05', $billing['acctInfo']['chAccAgeInd']);
        self::assertSame('01', $billing['acctInfo']['suspiciousAccActivity']);
        self::assertSame('9911234', $billing['mobilePhone']['subscriber']);
        self::assertSame('2611234', $billing['workPhone']['subscriber']);
    }

    public function testItAcceptsGatewayFieldNames(): void
    {
        $billing = Billing::from([
            'email' => 'customer@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Principal, 1',
            'billAddrPostCode' => '7600',
            'acctInfo' => ['chAccAgeInd' => '05'],
        ])->toArray();

        self::assertSame('132', $billing['billAddrCountry']);
        self::assertSame('05', $billing['acctInfo']['chAccAgeInd']);
    }

    public function testItRejectsUnknownFields(): void
    {
        $this->expectException(InvalidRequestException::class);
        $this->expectExceptionMessage('Campo de billing não permitido: unknown.');

        Billing::from(['unknown' => 'value']);
    }

    public function testItRejectsInvalidEmail(): void
    {
        $this->expectException(InvalidRequestException::class);

        Billing::make()->email('invalid-email');
    }

    public function testItRejectsInvalidAccountInformation(): void
    {
        $this->expectException(InvalidRequestException::class);

        Billing::make()->accountInfo(['unknown' => 'value']);
    }
}
