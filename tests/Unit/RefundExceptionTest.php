<?php

namespace Tests\Unit;

use Erilshk\Sisp\Core\Refund as Vinti4Refund;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RefundExceptionTest extends TestCase
{
    private Refund $refund;

    protected function setUp(): void
    {
        $this->refund = new Refund('TEST_POS_123', 'TEST_AUTH_456');
    }

    /** 
     * Testa campos obrigatórios faltando
     */
    public function testPreparePaymentMissingRequiredField()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Campo obrigatório faltando: transactionID");

        $this->refund->preparePayment([
            'amount' => 1500,
            // 'transactionID' => 'TXN789', // intencionalmente ausente
            'clearingPeriod' => '2411',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);
    }

    /**
     * Testa amount inválido (não inteiro)
     */
    public function testPreparePaymentInvalidAmount()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Amount deve ser inteiro, sem casas decimais.");

        $this->refund->preparePayment([
            'amount' => 1500.50, // valor decimal inválido
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'transactionID' => 'TXN789',
            'clearingPeriod' => '2411',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);
    }

    /**
     * Testa URL inválida
     */
    public function testPreparePaymentInvalidUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("urlMerchantResponse deve ser uma URL válida.");

        $this->refund->preparePayment([
            'amount' => 1500,
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'transactionID' => 'TXN789',
            'clearingPeriod' => '2411',
            'urlMerchantResponse' => 'not-a-valid-url' // inválida
        ]);
    }

    /**
     * Testa falha na validação de parâmetros internos (validateParams)
     */
    public function testPreparePaymentValidationError()
    {
        // Mock do método validateParams para simular erro
        $refund = $this->getMockBuilder(Refund::class)
            ->setConstructorArgs(['TEST_POS_123', 'TEST_AUTH_456'])
            ->onlyMethods(['validateParams'])
            ->getMock();

        $refund->method('validateParams')->willReturn('Erro de validação interno');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Erro de validação interno');

        $refund->preparePayment([
            'amount' => 1500,
            'merchantRef' => 'REF123',
            'merchantSession' => 'SESS456',
            'transactionID' => 'TXN789',
            'clearingPeriod' => '2411',
            'urlMerchantResponse' => 'https://callback.example.com'
        ]);
    }
}

// Extendendo Refund para testes públicos dos métodos protegidos
class Refund extends Vinti4Refund
{
    public function fingerprintRequest(array $data): string { return parent::fingerprintRequest($data); }
    public function fingerprintResponse(array $data): string { return parent::fingerprintResponse($data); }
}
