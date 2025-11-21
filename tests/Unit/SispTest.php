<?php

namespace Tests\Unit;

use Erilshk\Sisp\Core\Sisp;
use PHPUnit\Framework\TestCase;

// Classe concreta para testar Sisp abstrato
class ConcreteSisp extends Sisp
{
    protected function fingerprintRequest(array $data): string
    {
        return 'test_fingerprint_request';
    }

    protected function fingerprintResponse(array $data): string
    {
        return 'test_fingerprint_response';
    }

    public function preparePayment(array $params): array
    {
        return ['prepared' => true];
    }

    // Getters para teste
    public function getPosId(): string
    {
        return $this->posID;
    }
    public function getPosAuthCode(): string
    {
        return $this->posAuthCode;
    }
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }
    public function currencyToCode(string $currency): int
    {
        return parent::currencyToCode($currency);
    }
    public function normalizeBilling(array $billing): array
    {
        return parent::normalizeBilling($billing);
    }
    public function generatePurchaseRequest(array $billing): string
    {
        return parent::generatePurchaseRequest($billing);
    }
}

class SispTest extends TestCase
{
    private ConcreteSisp $sisp;

    protected function setUp(): void
    {
        $this->sisp = new ConcreteSisp('TEST_POS_123', 'TEST_AUTH_456');
    }

    public function testConstructorSetsProperties()
    {
        $this->assertEquals('TEST_POS_123', $this->sisp->getPosId());
        $this->assertEquals('TEST_AUTH_456', $this->sisp->getPosAuthCode());
        $this->assertEquals(Sisp::DEFAULT_BASE_URL, $this->sisp->getBaseUrl());
    }

    public function testConstructorWithCustomEndpoint()
    {
        $customEndpoint = 'https://custom.endpoint.cv';
        $sisp = new ConcreteSisp('POS123', 'AUTH456', $customEndpoint);

        $this->assertEquals($customEndpoint, $sisp->getBaseUrl());
    }

    public function testCurrencyToCode()
    {
        $this->assertEquals(132, $this->sisp->currencyToCode('CVE'));
        $this->assertEquals(132, $this->sisp->currencyToCode('cve'));
        $this->assertEquals(840, $this->sisp->currencyToCode('USD'));
        $this->assertEquals(978, $this->sisp->currencyToCode('EUR'));
        $this->assertEquals(986, $this->sisp->currencyToCode('BRL'));
        $this->assertEquals(132, $this->sisp->currencyToCode('132'));
    }

    public function testCurrencyToCodeThrowsExceptionForInvalidCurrency()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid currency: INVALID');

        $this->sisp->currencyToCode('INVALID');
    }

    public function testProcessResponseSuccess()
    {
        $postData = [
            'messageType' => '8',
            'resultFingerPrint' => 'test_fingerprint_response',
            'merchantRespTimeStamp' => '2024-01-01 12:00:00'
        ];

        $result = $this->sisp->processResponse($postData);

        $this->assertTrue($result['success']);
        $this->assertTrue($result['fingerprint_valid']);
        $this->assertEquals('8', $result['message_type']);
        $this->assertEquals($postData, $result['data']);
    }

    public function testProcessResponseInvalidFingerprint()
    {
        $postData = [
            'messageType' => '10',
            'resultFingerPrint' => 'invalid_fingerprint',
            'merchantRespTimeStamp' => '2024-01-01 12:00:00'
        ];

        $result = $this->sisp->processResponse($postData);

        $this->assertFalse($result['success']);
        $this->assertFalse($result['fingerprint_valid']);
        $this->assertEquals('10', $result['message_type']);
    }

    public function testProcessResponseInvalidMessageType()
    {
        $postData = [
            'messageType' => '99', // Tipo inválido
            'resultFingerPrint' => 'test_fingerprint_response',
            'merchantRespTimeStamp' => '2024-01-01 12:00:00'
        ];

        $result = $this->sisp->processResponse($postData);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['fingerprint_valid']);
    }

    public function testNormalizeBilling()
    {
        $billing = [
            'email' => 'test@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste 123',
            'user' => [
                'mobilePhone' => '+238 9912345',
                'created_at' => '2023-01-01 00:00:00'
            ]
        ];


        $normalized = $this->sisp->normalizeBilling($billing);

        $this->assertEquals('test@example.com', $normalized['email']);
        $this->assertEquals('132', $normalized['billAddrCountry']);
        $this->assertEquals('Praia', $normalized['billAddrCity']);
        $this->assertEquals('Rua Teste 123', $normalized['billAddrLine1']);
        $this->assertArrayHasKey('mobilePhone', $normalized);
    }

    public function testGeneratePurchaseRequest()
    {
        $billing = [
            'email' => 'test@example.com',
            'billAddrCountry' => '132',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste 123',
            'billAddrPostCode' => '7600'
        ];

        $purchaseRequest = $this->sisp->generatePurchaseRequest($billing);

        $this->assertIsString($purchaseRequest);
        $this->assertNotEmpty($purchaseRequest);

        // Deve ser base64 válido
        $decoded = base64_decode($purchaseRequest, true);
        $this->assertNotFalse($decoded);

        // Deve ser JSON válido
        $jsonData = json_decode($decoded, true);
        $this->assertIsArray($jsonData);
        $this->assertEquals('test@example.com', $jsonData['email']);
    }

    public function testGeneratePurchaseRequestThrowsExceptionForMissingFields()
    {
        $billing = [
            'email' => 'test@example.com',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua Teste',
            'billAddrPostCode' => '7600',
            # 'billAddrCountry' => '132',
            // Campos obrigatórios faltando
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Campos obrigatórios ausentes em billing: billAddrCountry.');

        $this->sisp->generatePurchaseRequest($billing);
    }
}
