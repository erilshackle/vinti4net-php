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
        self::assertStringContainsString('123456••••••3456', $html);
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

    public function testItRejectsIncompleteDccData(): void
    {
        $this->expectException(Vinti4Exception::class);

        Vinti4Response::success(data: [
            'merchantRespPurchaseAmount' => '1000',
        ])->renderDccReceipt();
    }

    public function testItRejectsAMissingTemplate(): void
    {
        $this->expectException(Vinti4Exception::class);

        $this->response()->renderReceipt(__DIR__ . '/missing.php');
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
                'merchantRespPan' => '1234567890123456',
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
