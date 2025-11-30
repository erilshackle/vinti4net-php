<?php

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Vinti4Net;
use Erilshk\Sisp\Core\Payment;
use Erilshk\Sisp\Core\Refund;

class Vinti4NetResponseTest extends TestCase
{
    private Vinti4Net $vinti;

    protected function setUp(): void
    {
        $this->vinti = new Vinti4Net('POS123', 'AUTH456', 'https://fake-endpoint.test');
    }

    public function testProcessResponsePaymentSuccess(): void
    {
        $postData = [
            'transactionCode' => '1',
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 1000,
            'resultFingerPrint' => base64_encode(hash('sha512', 'dummy', true)) // Simulado
        ];

        $response = $this->vinti->processResponse($postData);

        $this->assertIsObject($response);
        $this->assertArrayHasKey('success', (array)$response);
    }

    public function testProcessResponseRefundSuccess(): void
    {
        $postData = [
            'transactionCode' => '4',
            'messageType' => '8',
            'merchantRespPurchaseAmount' => 500,
            'resultFingerPrint' => base64_encode(hash('sha512', 'dummy', true)) // Simulado
        ];

        $response = $this->vinti->processResponse($postData);

        $this->assertIsObject($response);
        $this->assertArrayHasKey('success', (array)$response);
    }
}
