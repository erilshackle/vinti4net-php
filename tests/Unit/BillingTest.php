<?php

namespace Tests\Unit;

use Erilshk\Sisp\Billing;
use PHPUnit\Framework\TestCase;

class BillingTest extends TestCase
{
    public function testCreateGeneratesFullBillingArray()
    {
        $billing = Billing::create([
            'email' => 'test@example.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Rua XPTO',
            'address2' => 'Bloco A',
            'address3' => 'Apartamento 101',
            'postalCode' => '7600',
            'state' => 'SV',
            'shipCountry' => '132',
            'shipCity' => 'Praia',
            'shipAddress' => 'Rua YYY',
            'shipPostalCode' => '7601',
            'shipState' => 'SV',
            'mobilePhone' => ['cc' => '238', 'subscriber' => '99112233'],
            'workPhone' => ['cc' => '238', 'subscriber' => '99113344'],
            'acctID' => '12345',
            'acctInfo' => [
                'chAccAgeInd' => '05',
                'chAccChange' => '20230101',
                'chAccDate' => '20220101',
                'chAccPwChange' => '20230201',
                'chAccPwChangeInd' => '05',
                'suspiciousAccActivity' => '02',
            ],
            'suspicious' => true,
            'addrMatch' => true,
        ]);

        $this->assertEquals('test@example.com', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertEquals('Praia', $billing['billAddrCity']);
        $this->assertEquals('Rua XPTO', $billing['billAddrLine1']);
        $this->assertEquals('Bloco A', $billing['billAddrLine2']);
        $this->assertEquals('Apartamento 101', $billing['billAddrLine3']);
        $this->assertEquals('7600', $billing['billAddrPostCode']);
        $this->assertEquals('SV', $billing['billAddrState']);
        $this->assertEquals('Y', $billing['addrMatch']);
        $this->assertEquals(['cc' => '238', 'subscriber' => '99112233'], $billing['mobilePhone']);
        $this->assertEquals(['cc' => '238', 'subscriber' => '99113344'], $billing['workPhone']);
        $this->assertEquals('12345', $billing['acctID']);
        $this->assertEquals('02', $billing['acctInfo']['suspiciousAccActivity']);
        $this->assertTrue($billing['suspicious']);
    }

    public function testFluentSettersWork()
    {
        $billing = Billing::make()
            ->email('user@mail.com')
            ->country('840')
            ->city('New York')
            ->address('5th Avenue')
            ->address2('Apt 101')
            ->address3('Floor 2')
            ->postalCode('12345')
            ->state('NY')
            ->shipCountry('840')
            ->shipCity('New York')
            ->shipAddress('6th Avenue')
            ->shipPostalCode('12346')
            ->shipState('NY')
            ->addrMatch(false)
            ->mobilePhone('1', '(555) 123-4567')
            ->workPhone('1', '555-987-6543')
            ->acctID('abc123')
            ->acctInfo(['chAccAgeInd' => '05'])
            ->suspicious(true)
            ->toArray();

        $this->assertEquals('user@mail.com', $billing['email']);
        $this->assertEquals('840', $billing['billAddrCountry']);
        $this->assertEquals('5th Avenue', $billing['billAddrLine1']);
        $this->assertEquals('12345', $billing['billAddrPostCode']);
        $this->assertEquals('N', $billing['addrMatch']);
        $this->assertEquals('5551234567', $billing['mobilePhone']['subscriber']);
        $this->assertEquals('5559876543', $billing['workPhone']['subscriber']);
        $this->assertEquals('abc123', $billing['acctID']);
        $this->assertEquals('05', $billing['acctInfo']['chAccAgeInd']);
        $this->assertTrue($billing['suspicious']);
    }

    public function testPhoneNormalization()
    {
        $billing = Billing::make()->mobilePhone('238', '+238 991-11-22')->toArray();
        $this->assertEquals('2389911122', $billing['mobilePhone']['subscriber']);

        $billing = Billing::make()->workPhone('238', '(238) 912-2233')->toArray();
        $this->assertEquals('2389122233', $billing['workPhone']['subscriber']);
    }

    public function testFillAssignsStringPhoneToInternalArray()
    {
        $billing = Billing::create([
            'mobilePhone' => '9911-2233', // string → ativa linha 129
            'workPhone'   => '(238) 991-3344', // também ativa linha 129
        ]);

        $this->assertEquals(
            ['cc' => '238', 'subscriber' => '99112233'],
            $billing['mobilePhone']
        );

        $this->assertEquals(
            ['cc' => '238', 'subscriber' => '2389913344'],
            $billing['workPhone']
        );
    }


    public function testFromUserPopulatesAllFields()
    {
        $user = [
            'email' => 'user@test.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Rua A',
            'address2' => 'Bloco B',
            'address3' => 'Apt 5',
            'postCode' => '7600',
            'state' => 'SV',
            'mobilePhoneCC' => '238',
            'mobilePhone' => '99112233',
            'workPhoneCC' => '238',
            'workPhone' => '99113344',
            'id' => 'user123',
            'chAccAgeInd' => '05',
            'chAccPwInd' => '05',
            'suspicious' => true,
            'created_at' => '2022-01-01',
            'updated_at' => '2023-01-01',
        ];

        $billing = Billing::fromUser($user)->toArray();

        $this->assertEquals('user@test.com', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertEquals('Praia', $billing['billAddrCity']);
        $this->assertEquals('Rua A', $billing['billAddrLine1']);
        $this->assertEquals('7600', $billing['billAddrPostCode']);
        $this->assertEquals('99112233', $billing['mobilePhone']['subscriber']);
        $this->assertEquals('99113344', $billing['workPhone']['subscriber']);
        $this->assertEquals('user123', $billing['acctID']);
        $this->assertEquals('05', $billing['acctInfo']['chAccAgeInd']);
        $this->assertEquals('20230101', $billing['acctInfo']['chAccChange']);
        $this->assertEquals('20220101', $billing['acctInfo']['chAccDate']);
        $this->assertEquals('20230101', $billing['acctInfo']['chAccPwChange']);
        $this->assertEquals('05', $billing['acctInfo']['chAccPwChangeInd']);
        $this->assertEquals('02', $billing['acctInfo']['suspiciousAccActivity']);
        $this->assertTrue($billing['suspicious']);
    }

    public function testUnknownFieldsAreIgnored()
    {
        $billing = Billing::create([
            'email' => 'abc@test.com',
            'unknownField' => 'value',
        ]);

        $this->assertEquals('abc@test.com', $billing['email']);
        $this->assertArrayNotHasKey('unknownField', $billing);
    }
}
