<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Vinti4Response;

final class Vinti4ResponseReceiptTest extends TestCase
{
    // ---------------------------------------------------------
    //  PURCHASE (Compra)
    // ---------------------------------------------------------
    public function testPurchaseReceiptHtml(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'Transação válida.',
            success: true,
            data: [
                'messageType' => '8',
                'merchantRespPurchaseAmount' => '1500',
                'merchantRespCurrency' => 'CVE',
                'merchantRespTid' => 'TID123',
                'merchantRespMerchantRef' => 'REF001',
                'merchantRespTimeStamp' => '2025-11-30 12:00:00',
                'merchantRespPan' => '1234567890123456'
            ]
        );

        $html = $response->generateReceiptHtml('Minha Loja');

        $this->assertStringContainsString('COMPROVATIVO DE PAGAMENTO', $html);
        $this->assertStringContainsString('Compra', $html);
        $this->assertStringContainsString('Minha Loja', $html);
        $this->assertStringContainsString('TID123', $html);
        $this->assertStringContainsString('REF001', $html);
        $this->assertStringContainsString('••••3456', $html); // PAN mascarado
    }

    // ---------------------------------------------------------
    //  SERVICE PAYMENT (Pagamento de Serviço)
    // ---------------------------------------------------------
    public function testServiceReceiptHtml(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'Transação válida.',
            success: true,
            data: [
                'messageType' => 'P',
                'merchantRespEntityCode' => '10001', // ELECTRA
                'merchantRespReferenceNumber' => '556677'
            ]
        );

        $html = $response->generateReceiptHtml();

        $this->assertStringContainsString('Pagamento de serviço', $html);
        $this->assertStringContainsString('ELECTRA', $html);
        $this->assertStringContainsString('556677', $html);
    }

    // ---------------------------------------------------------
    //  RECHARGE (Recarga)
    // ---------------------------------------------------------
    public function testRechargeReceiptHtml(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'Transação válida.',
            success: true,
            data: [
                'messageType' => 'M',
                'merchantRespEntityCode' => '10021', // CVMÓVEL
                'merchantRespReferenceNumber' => '9912345'
            ]
        );

        $html = $response->generateReceiptHtml();

        $this->assertStringContainsString('Recarga de telemóvel', $html);
        $this->assertStringContainsString('CVMÓVEL', $html);
        $this->assertStringContainsString('+238 991 23 45', $html);
    }

    // ---------------------------------------------------------
    //  REFUND (Estorno)
    // ---------------------------------------------------------
    public function testRefundReceiptHtml(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'Reembolso processado com sucesso.',
            success: true,
            data: [
                'messageType' => '10',
                'merchantRespPurchaseAmount' => '12345',
                'merchantRespCurrency' => 'CVE'
            ]
        );

        $html = $response->generateReceiptHtml();

        $this->assertStringContainsString('COMPROVATIVO DE REEMBOLSO', $html);
        $this->assertStringContainsString('-12 345,00 CVE', $html);
    }

    // ---------------------------------------------------------
    //  FAILURE
    // ---------------------------------------------------------
    public function testReceiptHtmlFailure(): void
    {
        $response = new Vinti4Response(
            status: 'ERROR',
            message: 'Transação falhou.',
            success: false,
            data: [
                'messageType' => '8',
                'merchantRespAdditionalErrorMessage' => 'Saldo insuficiente'
            ]
        );

        $html = $response->generateReceiptHtml();

        $this->assertStringContainsString('RECIBO INDISPONÍVEL', $html);
        $this->assertStringContainsString('Saldo insuficiente', $html);
    }

    // ---------------------------------------------------------
    //  TEXT RECEIPT WITH DCC
    // ---------------------------------------------------------
    public function testTextReceiptWithDcc(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'Transação válida.',
            success: true,
            data: [
                'messageType' => '8',
                'merchantRespTid' => 'TID001',
                'merchantRespMerchantRef' => 'REF001',
                'merchantRespPurchaseAmount' => '10000',
                'merchantRespCurrency' => 'CVE'
            ],
            dcc: [
                'enabled' => true,
                'currency' => 'USD',
                'rate' => '1.2',
                'amount' => '100',
                'markup' => '5'
            ]
        );

        $text = $response->generateReceiptText();

        $this->assertStringContainsString('=== DCC (Moeda Estrangeira) ===', $text);
        $this->assertStringContainsString('USD', $text);
        $this->assertStringContainsString('1.2', $text);
        $this->assertStringContainsString('5%', $text);
    }

    // ---------------------------------------------------------
    //  ENTITY + SERVICE REF
    // ---------------------------------------------------------
    public function testTextReceiptIncludesEntityAndReference(): void
    {
        $response = new Vinti4Response(
            status: 'SUCCESS',
            message: 'OK',
            success: true,
            data: [
                'messageType' => 'P',
                'merchantRespEntityCode' => '10001',
                'merchantRespReferenceNumber' => '778899'
            ]
        );

        $text = $response->generateReceiptText();

        $this->assertStringContainsString('Entidade: ELECTRA', $text);
        $this->assertStringContainsString('Referência Serviço: 778899', $text);
    }
}
