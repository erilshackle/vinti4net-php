<?php

namespace Tests\Unit;

use Erilshk\Sisp\Core\Payment as Vinti4Payment;
use PHPUnit\Framework\TestCase;

class PaymentTest extends TestCase
{
    private Payment $payment;

    protected function setUp(): void
    {
        $this->payment = new Payment('TEST_POS_123', 'TEST_AUTH_456');
    }

    public function testPreparePurchasePayment()
    {
        $billing = [
            'email' => 'test@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '7600'
        ];

        $result = $this->payment->preparePayment([
            'amount' => 1500.50,
            'transactionCode' => '1',
            'billing' => $billing,
            'currency' => 'CVE',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);

        $this->assertArrayHasKey('postUrl', $result);
        $this->assertArrayHasKey('fields', $result);
        $this->assertStringContainsString($this->payment->getBaseUrl(), $result['postUrl']);
        
        $fields = $result['fields'];
        $this->assertEquals('TEST_POS_123', $fields['posID']);
        $this->assertEquals(1500, $fields['amount']);
        $this->assertEquals(132, $fields['currency']);
        $this->assertEquals('1', $fields['transactionCode']);
        $this->assertArrayHasKey('fingerprint', $fields);
        $this->assertArrayHasKey('purchaseRequest', $fields);
    }

    public function testPrepareServicePayment()
    {
        $result = $this->payment->preparePayment([
            'amount' => 2500,
            'transactionCode' => '2',
            'entityCode' => 10001,
            'referenceNumber' => '123456789',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);

        $fields = $result['fields'];
        $this->assertEquals('2', $fields['transactionCode']);
        $this->assertEquals(10001, $fields['entityCode']);
        $this->assertEquals('123456789', $fields['referenceNumber']);
        $this->assertArrayHasKey('fingerprint', $fields);
    }

    public function testPrepareRechargePayment()
    {
        $result = $this->payment->preparePayment([
            'amount' => 500,
            'transactionCode' => '3',
            'entityCode' => 10021,
            'referenceNumber' => '9912345',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);

        $fields = $result['fields'];
        $this->assertEquals('3', $fields['transactionCode']);
        $this->assertEquals(10021, $fields['entityCode']);
        $this->assertEquals('9912345', $fields['referenceNumber']);
    }

    public function testFingerprintRequestForPurchase()
    {
        $data = [
            'posID' => 'TEST_POS',
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'amount' => 1500,
            'currency' => 132,
            'transactionCode' => '1',
            'timeStamp' => '2024-01-01 12:00:00'
        ];

        $fingerprint = $this->payment->fingerprintRequest($data);

        $this->assertIsString($fingerprint);
        $this->assertNotEmpty($fingerprint);
        $this->assertEquals(88, strlen($fingerprint)); // Base64 SHA512
    }

    public function testFingerprintRequestForService()
    {
        $data = [
            'posID' => 'TEST_POS',
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'amount' => 1500,
            'currency' => 132,
            'transactionCode' => '2',
            'entityCode' => 10001,
            'referenceNumber' => '123456789',
            'timeStamp' => '2024-01-01 12:00:00'
        ];

        $fingerprint = $this->payment->fingerprintRequest($data);

        $this->assertIsString($fingerprint);
        $this->assertNotEmpty($fingerprint);
    }

    public function testFingerprintResponse()
    {
        $data = [
            'messageType' => '8',
            'merchantRespCP' => 'CP123',
            'merchantRespTid' => 'TXN456',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespMerchantSession' => 'SESS456',
            'merchantRespPurchaseAmount' => 1500.00,
            'merchantRespMessageID' => 'MSG789',
            'merchantRespPan' => '1234567890123456',
            'merchantResp' => 'APPROVED',
            'merchantRespTimeStamp' => '2024-01-01 12:00:00',
            'merchantRespReferenceNumber' => '123456789',
            'merchantRespEntityCode' => '10001',
            'merchantRespClientReceipt' => 'RECEIPT123',
            'merchantRespAdditionalErrorMessage' => '',
            'merchantRespReloadCode' => ''
        ];

        $fingerprint = $this->payment->fingerprintResponse($data);

        $this->assertIsString($fingerprint);
        $this->assertNotEmpty($fingerprint);
    }
}

// Adicionar método para teste
class Payment extends Vinti4Payment
{
    public function fingerprintRequest(array $data): string { return parent::fingerprintRequest($data); }
    public function fingerprintResponse(array $data): string { return parent::fingerprintResponse($data); }
    public function getBaseUrl(): string { return $this->baseUrl; }
}