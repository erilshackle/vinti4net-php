<?php

namespace  Tests\Integration;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Vinti4Net;
use Erilshk\Sisp\Billing;

class Vinti4NetFormTest extends TestCase
{
    private Vinti4Net $vinti;

    protected function setUp(): void
    {
        $this->vinti = new Vinti4Net('POS123', 'AUTH456', 'https://fake-endpoint.test');
    }

    public function testCreatePaymentFormReturnsHtmlWithFields(): void
    {
        $billing = Billing::create([
            'email' => 'customer@test.com',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Avenida Cidade da Praia, 45',
            'postalCode' => '7600'
        ]);

        $this->vinti->preparePurchase(1000, $billing);
        $html = $this->vinti->createPaymentForm('https://response.test');

        $this->assertStringContainsString('<form', $html);
        $this->assertStringNotContainsString("name='billAddrLine1'", $html);
        $this->assertStringNotContainsString("name='email'", $html);

        $this->assertSame(1, preg_match("/name='purchaseRequest' value='([^']+)'/", $html, $matches));
        $purchaseRequest = json_decode(base64_decode($matches[1], true), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('Avenida Cidade da Praia, 45', $purchaseRequest['billAddrLine1']);
        $this->assertSame('customer@test.com', $purchaseRequest['email']);
        $this->assertStringContainsString('https://response.test', $html);
    }

    public function testPurchaseWithoutBillingOmitsPurchaseRequest(): void
    {
        $this->vinti->preparePurchase(1000, []);
        $html = $this->vinti->createPaymentForm('https://response.test');

        $this->assertStringNotContainsString("name='purchaseRequest'", $html);
    }

    public function testCreatePaymentFormThrowsExceptionIfNotPrepared(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Nenhum pagamento preparado.');
        $this->vinti->createPaymentForm('https://response.test');
    }
}
