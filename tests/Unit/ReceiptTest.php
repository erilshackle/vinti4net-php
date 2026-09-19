<?php

declare(strict_types=1);

namespace Tests\Unit;

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Response;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    public function testItRendersTheDefaultReceipt(): void
    {
        $html = $this->response()->renderReceipt(data: [
            'companyName' => 'Minha Empresa',
            'logo' => 'https://example.com/logo.png',
        ]);

        self::assertStringContainsString('Comprovativo de pagamento', $html);
        self::assertStringContainsString('Minha Empresa', $html);
        self::assertStringContainsString('PURCHASE-0001', $html);
        self::assertStringContainsString('•••• 3456', $html);
        self::assertStringNotContainsString('1234567890123456', $html);
    }

    public function testLegacyHtmlMethodUsesTheNewReceiptClass(): void
    {
        $html = $this->response()->generateReceiptHtml('Minha Empresa', false);

        self::assertStringContainsString('Minha Empresa', $html);
        self::assertStringNotContainsString('<style>', $html);
    }

    public function testItRendersAnEscapedHtmlTemplate(): void
    {
        $html = $this->response()->renderReceipt(__DIR__ . '/../Fixtures/receipt.html', [
            'companyName' => '<Empresa>',
            'merchantReference' => 'FAKE',
        ]);

        self::assertStringContainsString('&lt;Empresa&gt;', $html);
        self::assertStringContainsString('PURCHASE-0001', $html);
        self::assertStringNotContainsString('FAKE', $html);
        self::assertStringContainsString('10.58 USD', $html);
    }

    public function testItRendersDccValuesWithoutChangingThem(): void
    {
        $html = $this->response()->renderDccReceipt();

        self::assertStringContainsString('1 USD = 92.65882 CVE', $html);
        self::assertStringContainsString('0.31 USD', $html);
        self::assertStringContainsString('1000 CVE', $html);
        self::assertStringContainsString('10.58 USD', $html);
        self::assertStringNotContainsString('0.31%', $html);
    }

    public function testItRendersARefundReceipt(): void
    {
        $response = Vinti4Response::success(
            data: [
                'messageType' => '10',
                'merchantRespTid' => 'REFUND-123',
                'merchantRespMerchantRef' => 'REF-0001',
                'merchantRespCurrency' => '132',
                'merchantRespTimeStamp' => '2026-09-05 13:00:00',
                'merchantRespMessageID' => 'AUTH-REFUND',
            ],
        );
    
        $html = $response->renderRefundReceipt(
            amount: 1000,
            originalTransactionId: 'TX-ORIGINAL-123',
            data: [
                'companyName' => 'Minha Empresa',
                'logo' => 'https://example.com/logo.png',
            ],
        );
    
        self::assertStringContainsString('Comprovativo de estorno', $html);
        self::assertStringContainsString('Minha Empresa', $html);
        self::assertStringContainsString('REF-0001', $html);
        self::assertStringContainsString('TX-ORIGINAL-123', $html);
        self::assertStringContainsString('REFUND-123', $html);
        self::assertStringContainsString('AUTH-REFUND', $html);
        self::assertStringContainsString('1000', $html);
        self::assertStringContainsString('CVE', $html);
        self::assertStringContainsString('Estorno processado com sucesso', $html);
    
        self::assertStringContainsString(
            'https://example.com/logo.png',
            $html,
        );
    
        self::assertStringContainsString('<style>', $html);
    }

    public function testItRendersATextReceipt(): void
    {
        $receipt = new Receipt($this->response());
    
        $text = $receipt->renderText([
            'companyName' => 'Minha Empresa',
        ]);
    
        self::assertStringContainsString(
            '==== RECIBO DE TRANSAÇÃO ====',
            $text,
        );
        self::assertStringContainsString(
            'Empresa: Minha Empresa',
            $text,
        );
        self::assertStringContainsString(
            'Data/Hora: 2026-09-05 12:30:00',
            $text,
        );
        self::assertStringContainsString(
            'Status: APROVADA',
            $text,
        );
        self::assertStringContainsString(
            'Transação ID: TX123456',
            $text,
        );
        self::assertStringContainsString(
            'Referência: PURCHASE-0001',
            $text,
        );
        self::assertStringContainsString(
            'Valor: 1000 CVE',
            $text,
        );
        self::assertStringContainsString(
            'Cartão: •••• 3456',
            $text,
        );
    
        self::assertStringContainsString(
            '=== DCC (Moeda Estrangeira) ===',
            $text,
        );
        self::assertStringContainsString(
            'Taxa de conversão: 1 USD = 92.65882 CVE',
            $text,
        );
        self::assertStringContainsString(
            'Taxa do serviço DCC: 0.31 USD',
            $text,
        );
        self::assertStringContainsString(
            'Total DCC: 10.58 USD',
            $text,
        );
    }

    public function testItRendersAMinimalRefundReceipt(): void
    {
        $response = Vinti4Response::success(
            data: [
                'messageType' => '10',
                'merchantRespTid' => 'REFUND-123',
                'merchantRespMerchantRef' => 'REF-0001',
                'merchantRespCurrency' => '132',
                'merchantRespTimeStamp' => '2026-09-05 13:00:00',
            ],
        );
    
        $html = $response->renderRefundReceipt(
            amount: '500',
            data: [
                'styled' => false,
            ],
        );
    
        self::assertStringContainsString('Comprovativo de estorno', $html);
        self::assertStringContainsString('Comerciante', $html);
    
        self::assertMatchesRegularExpression(
            '/500\s+CVE/',
            $html,
        );
    
        self::assertStringContainsString('N/A', $html);
    
        self::assertStringNotContainsString(
            'Transação original',
            $html,
        );
    
        self::assertStringNotContainsString('<style>', $html);
    }

    public function testItRejectsIncompleteDccData(): void
    {
        $this->expectException(Vinti4Exception::class);

        Vinti4Response::success(data: [
            'merchantRespPurchaseAmount' => '1000',
        ])->renderDccReceipt();
    }

    public function testItRejectsRefundReceiptForNonRefundResponse(): void
    {
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'O recibo de estorno exige uma resposta de estorno bem-sucedida.'
        );
    
        $this->response()->renderRefundReceipt(1000);
    }

    public function testItRejectsAMissingTemplate(): void
    {
        $this->expectException(Vinti4Exception::class);

        $this->response()->renderReceipt(__DIR__ . '/missing.php');
    }

    public function testItRejectsInvalidRefundAmount(): void
    {
        $response = Vinti4Response::success(
            data: [
                'messageType' => '10',
                'merchantRespTid' => 'REFUND-123',
            ],
        );
    
        $this->expectException(Vinti4Exception::class);
        $this->expectExceptionMessage(
            'O valor do estorno deve ser um inteiro positivo.'
        );
    
        $response->renderRefundReceipt('10.50');
    }

    public function testItRendersATextReceiptWithoutPanOrDcc(): void
    {
        $response = Vinti4Response::success(
            data: [
                'merchantRespPurchaseAmount' => '500',
                'merchantRespCurrency' => '132',
            ],
        );
    
        $receipt = new Receipt($response);
    
        $text = $receipt->renderText();
    
        self::assertStringContainsString(
            'Empresa: Comerciante/Entidade',
            $text,
        );
        self::assertStringContainsString(
            'Data/Hora: N/A',
            $text,
        );
        self::assertStringContainsString(
            'Transação ID: N/A',
            $text,
        );
        self::assertStringContainsString(
            'Referência: N/A',
            $text,
        );
        self::assertStringContainsString(
            'Valor: 500 CVE',
            $text,
        );
    
        self::assertStringNotContainsString('Cartão:', $text);
        self::assertStringNotContainsString(
            'DCC (Moeda Estrangeira)',
            $text,
        );
    }

    private function response(): Vinti4Response
    {
        return Vinti4Response::success(
            data: [
                'messageType' => '8',
                'merchantRespTid' => 'TX123456',
                'merchantRespMerchantRef' => 'PURCHASE-0001',
                'merchantRespPurchaseAmount' => '1000',
                'merchantRespCurrency' => '132',
                'merchantRespTimeStamp' => '2026-09-05 12:30:00',
                'merchantRespPan' => '************3456',
                'merchantRespMessageID' => 'AUTH001',
            ],
            dcc: [
                'enabled' => true,
                'amount' => '10.58',
                'currency' => 'USD',
                'markup' => '0.31',
                'rate' => '92.65882',
            ],
        );
    }

}
