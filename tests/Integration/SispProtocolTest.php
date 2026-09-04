<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Integration;

use Eril\Sisp\Core\Payment;
use Eril\Sisp\Core\Refund;
use PHPUnit\Framework\TestCase;

final class SispProtocolTest extends TestCase
{
    private const PURCHASE_FINGERPRINT =
        'af0i9XFZ5UOWEe8A4mUsVAfEKiTLPkvt2eE0j9VKLQ0jSihaPdco0OhNZZ+qQnnu2rgpfrDRVubCCnuBMctJtA==';

    private const REFUND_FINGERPRINT =
        'AnevOOAlWCGFNP0NLXDU2h3D1RQG3MguWL1JYlxHu5gdv80R7SLAJrXzHXhuLDcYt94u5Zzv4XjYR64cZx0qZg==';

    private const RESPONSE_FINGERPRINT =
        'fxAwI/o2ynv8kAybAaDGIB1DijOshoVY1EYzTskc/qUgVovVT0FjUpRlcfXhhLT/FF/IdsbBKrwr7ojNmulSxg==';

    public function testPurchaseRequestMatchesTheProtocolVector(): void
    {
        $result = $this->payment()->preparePayment([
            'transactionCode' => '1',
            'amount' => 1500,
            'merchantRef' => 'PURCHASE-001',
            'merchantSession' => 'SESSION-001',
            'currency' => 'CVE',
            'timeStamp' => '2026-09-04 12:00:00',
            'languageMessages' => 'pt',
            'urlMerchantResponse' => 'https://merchant.test/callback',
            'billing' => [
                'email' => 'customer@example.com',
                'billAddrCountry' => '132',
                'billAddrCity' => 'Praia',
                'billAddrLine1' => 'Rua Principal, 1',
                'billAddrPostCode' => '7600',
            ],
        ]);

        self::assertSame('1500', $result['fields']['amount']);
        self::assertSame('132', $result['fields']['currency']);
        self::assertSame(self::PURCHASE_FINGERPRINT, $result['fields']['fingerprint']);
    }

    public function testRefundRequestMatchesTheProtocolVector(): void
    {
        $result = $this->refund()->preparePayment([
            'amount' => 1500,
            'merchantRef' => 'REFUND-001',
            'merchantSession' => 'SESSION-001',
            'transactionID' => 'TX123456',
            'clearingPeriod' => '2609',
            'timeStamp' => '2026-09-04 12:00:00',
            'languageMessages' => 'pt',
            'urlMerchantResponse' => 'https://merchant.test/callback',
        ]);

        self::assertSame('4', $result['fields']['transactionCode']);
        self::assertSame('132', $result['fields']['currency']);
        self::assertSame(self::REFUND_FINGERPRINT, $result['fields']['fingerprint']);
    }

    public function testItValidatesARealResponseFingerprintVector(): void
    {
        $result = $this->payment()->processResponse([
            'messageType' => '8',
            'merchantRespCP' => '2609',
            'merchantRespTid' => 'TX123456',
            'merchantRespMerchantRef' => 'PURCHASE-001',
            'merchantRespMerchantSession' => 'SESSION-001',
            'merchantRespPurchaseAmount' => '1500',
            'merchantRespMessageID' => 'AUTH001',
            'merchantRespPan' => '1234567890123456',
            'merchantResp' => '00',
            'merchantRespTimeStamp' => '2026-09-04 12:30:00',
            'merchantRespClientReceipt' => 'CLIENT RECEIPT',
            'resultFingerPrint' => self::RESPONSE_FINGERPRINT,
        ]);

        self::assertTrue($result['fingerprint_valid']);
        self::assertTrue($result['success']);
    }

    private function payment(): Payment
    {
        return new Payment(
            'TEST_POS',
            'TEST_AUTH',
            'https://provider.test/payment',
        );
    }

    private function refund(): Refund
    {
        return new Refund(
            'TEST_POS',
            'TEST_AUTH',
            'https://provider.test/payment',
        );
    }
}
