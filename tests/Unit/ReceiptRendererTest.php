<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Erilshk\Sisp\Traits\ReceiptRenderer;
use Erilshk\Sisp\Core\Sisp;

final class ReceiptRendererTest extends TestCase
{
    private $renderer;

    protected function setUp(): void
    {
        $this->renderer = new class {
            use ReceiptRenderer;

            public $data = [];
            public $status = 'SUCCESS';
            public $dcc = [];
            public $success = true;

            public function getAmount()
            {
                return $this->data['amount'] ?? 123.45;
            }

            public function getCurrency()
            {
                return $this->data['currency'] ?? 'CVE';
            }
        };
    }

    // ===========================
    // HTML Receipts
    // ===========================

    public function testGenerateReceiptHtmlPurchase(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = ['messageType' => '8', 'merchantRespMerchantRef' => 'REF001'];
        $html = $this->renderer->generateReceiptHtml('Minha Loja');
        $this->assertStringContainsString('COMPROVATIVO DE PAGAMENTO', $html);
        $this->assertStringContainsString('Minha Loja', $html);
    }

    public function testGenerateReceiptHtmlService(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = [
            'messageType' => 'P',
            'entityCode' => '10001',
            'merchantRespReferenceNumber' => '1234'
        ];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('ELECTRA', $html);
        $this->assertStringContainsString('Pagamento de serviço', $html);
    }

    public function testGenerateReceiptHtmlRecharge(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = [
            'messageType' => 'M',
            'entityCode' => '10021',
            'merchantRespReferenceNumber' => '9912345'
        ];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('CVMÓVEL', $html);
        $this->assertStringContainsString('Recarga de telemóvel', $html);
    }

    public function testGenerateReceiptHtmlRefund(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = ['messageType' => '10', 'amount' => 123.45];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('COMPROVATIVO DE REEMBOLSO', $html);
        $this->assertStringContainsString('-123,45 CVE', $html);
    }

    public function testGenerateReceiptHtmlGeneric(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = ['messageType' => 'UNKNOWN_CODE'];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('RECIBO INDISPONÍVEL', $html);
    }

    public function testGenerateReceiptHtmlFailsForUnsuccessfulTransaction(): void
    {
        $this->renderer->success = false;
        $this->renderer->data = ['messageType' => '8'];
        $html = $this->renderer->generateReceiptHtml('Minha Loja');
        $this->assertStringContainsString('RECIBO INDISPONÍVEL', $html);
        $this->assertStringContainsString('Transação não concluída com sucesso', $html);
    }

    public function testGenerateReceiptHtmlFailsForUnknownType(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = ['messageType' => 'UNKNOWN_TYPE'];
        $html = $this->renderer->generateReceiptHtml('Minha Loja');
        $this->assertStringContainsString('RECIBO INDISPONÍVEL', $html);
        $this->assertStringContainsString('Recibo indisponível para este tipo de transação', $html);
    }

    public function testGenerateReceiptHtmlWithSimpleStyle(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = ['messageType' => '8'];
        $html = $this->renderer->generateReceiptHtml(null, false);
        $this->assertStringContainsString('font-family: courier, monospace', $html);
    }

    public function testRenderDccSection(): void
    {
        $this->renderer->dcc = [
            'enabled' => true,
            'currency' => 'USD',
            'rate' => 1.2,
            'amount' => 100,
            'markup' => 5
        ];
        $this->renderer->success = true;
        $this->renderer->data['messageType'] = '8';
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('Pagamento em moeda estrangeira', $html);
        $this->assertStringContainsString('1 USD = 1.2 CVE', $html);
    }

    public function testStatusRendering(): void
    {
        foreach (['SUCCESS', 'CANCELLED', 'INVALID_FINGERPRINT', 'UNKNOWN'] as $status) {
            $this->renderer->status = $status;
            $this->renderer->success = true;
            $this->renderer->data['messageType'] = '8';
            $html = $this->renderer->generateReceiptHtml();
            $this->assertNotEmpty($html);
        }
    }

    // ===========================
    // Text Receipts
    // ===========================

    public function testGenerateReceiptTextSuccess(): void
    {
        $this->renderer->success = true;
        $this->renderer->data = [
            'messageType' => '8',
            'merchantRespTid' => '123456',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespTimeStamp' => '2025-11-30 12:00:00',
            'merchantRespPan' => '1234567890123456',
            'merchantRespMessageID' => 'AUTH001'
        ];

        $text = $this->renderer->generateReceiptText('Minha Loja');
        $this->assertStringContainsString('==== RECIBO DE TRANSAÇÃO ====', $text);
        $this->assertStringContainsString('Minha Loja', $text);
        $this->assertStringContainsString('APROVADA', $text);
        $this->assertStringContainsString('Compra', $text);
        $this->assertStringContainsString('123456', $text);
        $this->assertStringContainsString('REF123', $text);
        $this->assertStringContainsString('••••3456', $text);
    }

    public function testGenerateReceiptTextFailure(): void
    {
        $this->renderer->success = false;
        $this->renderer->data = [
            'messageType' => '8',
            'merchantRespTid' => '123456',
            'merchantRespMerchantRef' => 'REF123',
            'merchantRespAdditionalErrorMessage' => 'Saldo insuficiente',
            'merchantRespTimeStamp' => '2025-11-30 12:00:00'
        ];

        $text = $this->renderer->generateReceiptText('Minha Loja');
        $this->assertStringContainsString('NÃO CONCLUÍDA', $text);
        $this->assertStringContainsString('Saldo insuficiente', $text);
    }

    public function testGenerateReceiptTextWithDcc(): void
    {
        $this->renderer->success = true;
        $this->renderer->dcc = [
            'enabled' => true,
            'currency' => 'USD',
            'rate' => 1.2,
            'amount' => 100,
            'markup' => 5
        ];
        $this->renderer->data = [
            'messageType' => '8',
            'merchantRespTid' => 'TID001',
            'merchantRespMerchantRef' => 'REF001',
        ];

        $text = $this->renderer->generateReceiptText('Minha Loja');
        $this->assertStringContainsString('=== DCC (Moeda Estrangeira) ===', $text);
        $this->assertStringContainsString('1.2', $text);
        $this->assertStringContainsString('USD', $text);
        $this->assertStringContainsString('5%', $text);
    }

    // ===========================
    // Misc / Private Methods
    // ===========================

    public function testFormatCurrencyEURAndDefault(): void
    {
        $method = new \ReflectionMethod($this->renderer, 'formatCurrency');
        $method->setAccessible(true);

        $result = $method->invoke($this->renderer, 100, 'EUR');
        $this->assertStringContainsString('EUR', $result);

        $result = $method->invoke($this->renderer, 100, 'XYZ');
        $this->assertStringContainsString('XYZ', $result);
    }

    public function testFormatTimestampCatch(): void
    {
        $method = new \ReflectionMethod($this->renderer, 'formatTimestamp');
        $method->setAccessible(true);
        $result = $method->invoke($this->renderer, 'INVALID_TIMESTAMP');
        $this->assertEquals('N/A', $result);
    }

    public function testFormatPhoneNumber(): void
    {
        $method = new \ReflectionMethod($this->renderer, 'formatPhoneNumber');
        $method->setAccessible(true);

        $phone = '9912345';
        $formatted = $method->invoke($this->renderer, $phone);
        $this->assertStringContainsString('+238', $formatted);

        $formatted = $method->invoke($this->renderer, '');
        $this->assertEquals('N/A', $formatted);

        $formatted = $method->invoke($this->renderer, '+1234567890');
        $this->assertEquals('+1234567890', $formatted);
    }

    public function testGetEntityName(): void
    {
        $method = new \ReflectionMethod($this->renderer, 'getEntityName');
        $method->setAccessible(true);

        $this->assertEquals('ÁGUAS DE CABO VERDE', $method->invoke($this->renderer, '10002'));
        $this->assertEquals('UNITEL T+', $method->invoke($this->renderer, '10022'));
        $this->assertEquals('Entidade', $method->invoke($this->renderer, '99999'));
    }

    public function testGetEntityContact(): void
    {
        $method = new \ReflectionMethod($this->renderer, 'getEntityContact');
        $method->setAccessible(true);

        $this->assertEquals('Contacto: 262 30 60', $method->invoke($this->renderer, '10001'));
        $this->assertEquals('Contacto: 800 20 20', $method->invoke($this->renderer, '10002'));
        $this->assertEquals('Contacto: 111', $method->invoke($this->renderer, '10021'));
        $this->assertEquals('Contacto: 101', $method->invoke($this->renderer, '10022'));
        $this->assertEquals('', $method->invoke($this->renderer, '99999'));
    }
}
