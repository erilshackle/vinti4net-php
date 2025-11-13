<?php

namespace Tests\Unit;

use Erilshk\Vinti4Net\Core\Refund as Vinti4Refund;
use PHPUnit\Framework\TestCase;

class RefundTest extends TestCase
{
    private Refund $refund;

    protected function setUp(): void
    {
        $this->refund = new Refund('TEST_POS_123', 'TEST_AUTH_456');
    }

    public function testPrepareRefundPayment()
    {
        $result = $this->refund->preparePayment([
            'amount' => 1500,
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'transactionID' => 'TXN789',
            'clearingPeriod' => '2024-11'
        ]);

        $this->assertArrayHasKey('postUrl', $result);
        $this->assertArrayHasKey('fields', $result);
        
        $fields = $result['fields'];
        $this->assertEquals('TEST_POS_123', $fields['posID']);
        $this->assertEquals(1500, $fields['amount']);
        $this->assertEquals('4', $fields['transactionCode']);
        $this->assertEquals('REF123', $fields['merchantRef']);
        $this->assertEquals('SESS456', $fields['merchantSession']);
        $this->assertEquals('TXN789', $fields['transactionID']);
        $this->assertEquals('2024-11', $fields['clearingPeriod']);
        $this->assertArrayHasKey('fingerprint', $fields);
    }

    public function testFingerprintRequest()
    {
        $data = [
            'transactionCode' => '4',
            'posID' => 'TEST_POS',
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'amount' => 1500,
            'currency' => 132,
            'clearingPeriod' => '2024-11',
            'transactionID' => 'TXN789',
            'urlMerchantResponse' => 'https://callback.example.com',
            'languageMessages' => 'pt',
            'timeStamp' => '2024-01-01 12:00:00'
        ];

        $fingerprint = $this->refund->fingerprintRequest($data);

        $this->assertIsString($fingerprint);
        $this->assertNotEmpty($fingerprint);
    }

    public function testFingerprintResponse()
    {
        $data = [
            'messageType' => '8',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SESS456',
            'merchantRespPurchaseAmount' => 1500.00,
            'merchantRespMessageID' => 'MSG789',
            'merchantResp' => 'REFUNDED',
            'merchantRespTimeStamp' => '2024-01-01 12:00:00',
            'merchantRespTransactionID' => 'TXN789',
            'merchantRespClearingPeriod' => '2024-11'
        ];

        $fingerprint = $this->refund->fingerprintResponse($data);

        $this->assertIsString($fingerprint);
        $this->assertNotEmpty($fingerprint);
    }
}

// Adicionar método para teste
class Refund extends Vinti4Refund
{
    public function fingerprintRequest(array $data): string { return parent::fingerprintRequest($data); }
    public function fingerprintResponse(array $data): string { return parent::fingerprintResponse($data); }
}