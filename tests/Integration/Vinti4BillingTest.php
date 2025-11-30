<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Billing;

class Vinti4BillingTest extends TestCase
{
    public function testBillingCreateReturnsArrayWithExpectedKeys(): void
    {
        $billing = Billing::create([
            'email' => 'test@example.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Rua Principal, 1',
            'postalCode' => '7600'
        ]);

        $this->assertIsArray($billing);
        $this->assertEquals('test@example.com', $billing['email']);
        $this->assertEquals('132', $billing['billAddrCountry']);
        $this->assertEquals('Praia', $billing['billAddrCity']);
        $this->assertEquals('Rua Principal, 1', $billing['billAddrLine1']);
    }

    public function testMobilePhoneAndWorkPhoneNormalized(): void
    {
        $billing = Billing::create([
            'mobilePhone' => '+23891234567',
            'workPhone' => '91234567'
        ]);

        $this->assertEquals('238', $billing['mobilePhone']['cc']);
        $this->assertEquals('23891234567', $billing['mobilePhone']['subscriber']);
        $this->assertEquals('238', $billing['workPhone']['cc']);
        $this->assertEquals('91234567', $billing['workPhone']['subscriber']);
    }
}
