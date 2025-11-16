<?php

namespace Erilshk\Vinti4Net\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Erilshk\Vinti4Net\Traits\ReceiptRenderer;
use Erilshk\Vinti4Net\Core\Sisp;

final class ReceiptRendererTest extends TestCase
{
    /**
     * Classe de teste que usa a trait
     */
    private $renderer;

    protected function setUp(): void
    {
        $this->renderer = new class {
            use ReceiptRenderer;

            public $data = [];
            public $status = 'SUCCESS';
            public $dcc = [];

            // mocks para métodos privados acessados na trait
            public function getAmount() { return $this->data['amount'] ?? 123.45; }
            public function getCurrency() { return $this->data['currency'] ?? 'CVE'; }
        };
    }

    public function testGenerateReceiptHtmlPurchase(): void
    {
        $this->renderer->data = ['transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE];
        $html = $this->renderer->generateReceiptHtml('Minha Loja');
        $this->assertStringContainsString('COMPROVATIVO DE PAGAMENTO', $html);
        $this->assertStringContainsString('Minha Loja', $html);
    }

    public function testGenerateReceiptHtmlService(): void
    {
        $this->renderer->data = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_SERVICE,
            'entityCode' => '10001',
            'referenceNumber' => '1234'
        ];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('ELECTRA', $html);
        $this->assertStringContainsString('Pagamento de serviço', $html);
    }

    public function testGenerateReceiptHtmlRecharge(): void
    {
        $this->renderer->data = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_RECHARGE,
            'entityCode' => '10021',
            'referenceNumber' => '9912345'
        ];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('CVMÓVEL', $html);
        $this->assertStringContainsString('Recarga de telemóvel', $html);
    }

    public function testGenerateReceiptHtmlRefund(): void
    {
        $this->renderer->data = ['transactionCode' => Sisp::TRANSACTION_TYPE_REFUND];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('COMPROVATIVO DE REEMBOLSO', $html);
        $this->assertStringContainsString('-123,45 CVE', $html);
    }

    public function testGenerateReceiptHtmlGeneric(): void
    {
        $this->renderer->data = ['transactionCode' => 'UNKNOWN_CODE'];
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('COMPROVATIVO DE TRANSAÇÃO', $html);
    }

    public function testGenerateReceiptHtmlWithSimpleStyle(): void
    {
        $this->renderer->data = ['transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE];
        $html = $this->renderer->generateReceiptHtml(null, true);
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
        $this->renderer->data['transactionCode'] = Sisp::TRANSACTION_TYPE_PURCHASE;
        $html = $this->renderer->generateReceiptHtml();
        $this->assertStringContainsString('Pagamento em moeda estrangeira', $html);
        $this->assertStringContainsString('1 USD = 1.2 CVE', $html);
    }

    public function testStatusRendering(): void
    {
        foreach (['SUCCESS', 'CANCELLED', 'INVALID_FINGERPRINT', 'UNKNOWN'] as $status) {
            $this->renderer->status = $status;
            $html = $this->renderer->generateReceiptHtml();
            $this->assertNotEmpty($html);
        }
    }
}

