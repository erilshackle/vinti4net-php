<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Unit;

use Eril\Sisp\Exception\ReceiptException;
use Eril\Sisp\Vinti4Response;
use PHPUnit\Framework\TestCase;

final class ReceiptTest extends TestCase
{
    public function testItRendersTheDefaultReceipt(): void
    {
        $html = $this->response()->renderDefaultReceipt(
            companyName: 'Minha Empresa',
            logo: 'https://example.com/logo.png',
        );

        self::assertStringContainsString('Comprovativo de pagamento', $html);
        self::assertStringContainsString('Minha Empresa', $html);
        self::assertStringContainsString('PURCHASE-001', $html);
        self::assertStringContainsString('123456••••••3456', $html);
        self::assertStringContainsString('Transação aprovada', $html);
    }

    public function testItRendersACustomPhpTemplate(): void
    {
        $html = $this->response()->renderReceipt(
            __DIR__ . '/../Fixtures/receipt.php',
            ['title' => 'Recibo Personalizado'],
        );

        self::assertStringContainsString('class="custom-receipt"', $html);
        self::assertStringContainsString('Recibo Personalizado', $html);
        self::assertStringContainsString('PURCHASE-001', $html);
    }

    public function testItRendersEscapedHtmlPlaceholders(): void
    {
        $html = $this->response()->renderReceipt(
            __DIR__ . '/../Fixtures/receipt.html',
            ['companyName' => '<script>alert("xss")</script>'],
        );

        self::assertStringContainsString(
            '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;',
            $html,
        );
        self::assertStringContainsString('15.50 EUR', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('{{', $html);
    }

    public function testTransactionDataCannotBeOverridden(): void
    {
        $html = $this->response()->renderReceipt(
            __DIR__ . '/../Fixtures/receipt.html',
            ['merchantReference' => 'FAKE'],
        );

        self::assertStringContainsString('PURCHASE-001', $html);
        self::assertStringNotContainsString('FAKE', $html);
    }

    public function testItRejectsAMissingTemplate(): void
    {
        $this->expectException(ReceiptException::class);

        $this->response()->renderReceipt(__DIR__ . '/missing.php');
    }

    private function response(): Vinti4Response
    {
        return Vinti4Response::success(
            data: [
                'messageType' => '8',
                'transactionCode' => '1',
                'merchantRespTid' => 'TX123456',
                'merchantRespMerchantRef' => 'PURCHASE-001',
                'merchantRespPurchaseAmount' => '1500',
                'merchantRespCurrency' => '132',
                'merchantRespPan' => '1234567890123456',
            ],
            dcc: [
                'enabled' => true,
                'amount' => '15.50',
                'currency' => 'EUR',
                'markup' => '3.5',
                'rate' => '0.0103',
            ],
        );
    }
}
