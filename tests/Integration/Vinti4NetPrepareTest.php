<?php

namespace  Tests\Integration;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Vinti4Net;
use Erilshk\Sisp\Billing;

class Vinti4NetPrepareTest extends TestCase
{
    private Vinti4Net $vinti;

    protected function setUp(): void
    {
        $this->vinti = new Vinti4Net('POS123', 'AUTH456', 'https://fake-endpoint.test');
    }

    public function testPreparePurchaseSetsRequestCorrectly(): void
    {
        $billing = Billing::create([
            'email' => 'customer@test.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Avenida Cidade da Praia, 45',
            'postalCode' => '7600'
        ]);

        $this->vinti->preparePurchase(1000, $billing);

        $request = $this->vinti->getRequest();

        $this->assertEquals('1', $request['transactionCode']);
        $this->assertEquals(1000, $request['amount']);
        $this->assertEquals('CVE', $request['currency']);
        $this->assertArrayHasKey('billing', $request);
        // $this->assertArrayHasKey('purchaseRequest', $request);
    }

    public function testPrepareServicePaymentSetsRequestCorrectly(): void
    {
        $this->vinti->prepareServicePayment(500, 123, '000001234');
        $request = $this->vinti->getRequest();

        $this->assertEquals('2', $request['transactionCode']);
        $this->assertEquals(500, $request['amount']);
        $this->assertEquals(123, $request['entityCode']);
        $this->assertEquals('000001234', $request['referenceNumber']);
    }

    public function testPrepareRechargeSetsRequestCorrectly(): void
    {
        $this->vinti->prepareRecharge(250, 321, '987654321');
        $request = $this->vinti->getRequest();

        $this->assertEquals('3', $request['transactionCode']);
        $this->assertEquals(250, $request['amount']);
        $this->assertEquals(321, $request['entityCode']);
        $this->assertEquals('987654321', $request['referenceNumber']);
    }

    public function testPrepareRefundSetsRequestCorrectly(): void
    {
        $this->vinti->prepareRefund(150, 'TX12345', '001');
        $request = $this->vinti->getRequest();

        $this->assertEquals('4', $request['transactionCode']);
        $this->assertEquals(150, $request['amount']);
        $this->assertEquals('001', $request['clearingPeriod']);
        $this->assertEquals('TX12345', $request['transactionID']);
    }
}
