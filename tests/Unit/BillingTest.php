<?php

namespace Tests\Unit;

use Erilshk\Vinti4Net\Billing;
use PHPUnit\Framework\TestCase;

class BillingTest extends TestCase
{
    public function testCreateGeneratesValidBillingArray()
    {
        $billing = Billing::create([
            'email'      => 'test@example.com',
            'country'    => '132',
            'city'       => 'Praia',
            'address'    => 'Rua XPTO',
            'postalCode' => '7600',
            'phone'      => '2389912244',
            'acctInfo'   => ['a' => 1],
            'suspicious' => true,
        ]);

        $this->assertEquals('test@example.com', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertEquals('Praia', $billing['billAddrCity']);
        $this->assertEquals('Rua XPTO', $billing['billAddrLine1']);
        $this->assertEquals('7600', $billing['billAddrPostCode']);
        $this->assertEquals('2389912244', $billing['mobilePhone']); // normalizado
        $this->assertEquals(['a' => 1], $billing['acctInfo']);
        $this->assertTrue($billing['suspicious']);
    }

    public function testCreateUsesFallbacksWhenFieldsAreMissing()
    {
        $billing = Billing::create([]);

        $this->assertEquals('', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertEquals('', $billing['billAddrCity']);
        $this->assertEquals('', $billing['billAddrLine1']);
        $this->assertEquals('', $billing['mobilePhone']);
        $this->assertFalse($billing['suspicious']);
    }

    public function testFluentBuilderCreatesCorrectBilling()
    {
        $billing = Billing::make()
            ->email('user@mail.com')
            ->country('840')
            ->city('New York')
            ->address('5th Avenue')
            ->postalCode('12345')
            ->mobilePhone('(238) 999-0000')
            ->toArray();

        $this->assertEquals('user@mail.com', $billing['email']);
        $this->assertEquals('840', $billing['billAddrCountry']);
        $this->assertEquals('New York', $billing['billAddrCity']);
        $this->assertEquals('5th Avenue', $billing['billAddrLine1']);
        $this->assertEquals('12345', $billing['billAddrPostCode']);
        $this->assertEquals('2389990000', $billing['mobilePhone']); // normalizado
    }

    public function testPhoneNormalizationRemovesNonNumericChars()
    {
        $billing = Billing::make()->mobilePhone('+238 991-11-22')->toArray();

        $this->assertEquals('2389911122', $billing['mobilePhone']);
    }

    public function testAdditionalFieldsDoNotBreak()
    {
        $billing = Billing::create([
            'unknownField' => 'test',
            'email' => 'abc@test.com'
        ]);

        $this->assertEquals('abc@test.com', $billing['email']);
        $this->assertArrayNotHasKey('unknownField', $billing);
    }
}
