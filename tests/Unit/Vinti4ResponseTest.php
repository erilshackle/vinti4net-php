<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Unit;

use Eril\Sisp\Exception\InvalidResponseException;
use Eril\Sisp\Vinti4Response;
use PHPUnit\Framework\TestCase;

final class Vinti4ResponseTest extends TestCase
{
    public function testItNormalizesASuccessfulResponse(): void
    {
        $response = $this->response();

        self::assertTrue($response->isValid());
        self::assertTrue($response->isSuccess());
        self::assertFalse($response->isCancelled());
        self::assertFalse($response->hasFailed());
        self::assertSame(Vinti4Response::STATUS_SUCCESS, $response->status());
        self::assertSame('purchase', $response->transactionType());
        self::assertSame('TX123456', $response->transactionId());
        self::assertSame('PURCHASE-001', $response->merchantReference());
        self::assertSame('1500', $response->amount());
        self::assertSame('CVE', $response->currency());
    }

    public function testSafeOutputDoesNotExposeRawCardData(): void
    {
        $response = $this->response();
        $safe = $response->toArray();

        self::assertArrayNotHasKey('data', $safe);
        self::assertArrayNotHasKey('merchantRespPan', $safe);
        self::assertStringNotContainsString('1234567890123456', $response->toJson());
        self::assertSame('1234567890123456', $response->raw()['merchantRespPan']);
        self::assertSame('123456••••••3456', $response->receiptData()['maskedPan']);
    }

    public function testItNormalizesDccData(): void
    {
        self::assertSame([
            'enabled' => true,
            'amount' => '15.50',
            'currency' => 'EUR',
            'markup' => '3.5',
            'rate' => '0.0103',
        ], $this->response()->dcc());
    }

    public function testInvalidFingerprintTakesPriorityOverCancellation(): void
    {
        $response = Vinti4Response::fromProcessorResult([
            'success' => false,
            'fingerprint_valid' => false,
            'data' => ['messageType' => '8', 'UserCancelled' => 'true'],
        ]);

        self::assertFalse($response->isValid());
        self::assertFalse($response->isCancelled());
        self::assertTrue($response->hasInvalidFingerprint());
    }

    public function testItDistinguishesCancelledAndFailedResponses(): void
    {
        $cancelled = Vinti4Response::fromProcessorResult([
            'success' => false,
            'fingerprint_valid' => true,
            'data' => ['messageType' => '8', 'UserCancelled' => 'true'],
        ]);

        $failed = Vinti4Response::fromProcessorResult([
            'success' => false,
            'fingerprint_valid' => true,
            'data' => [
                'messageType' => '8',
                'merchantRespErrorDescription' => 'Saldo insuficiente.',
            ],
        ]);

        self::assertTrue($cancelled->isCancelled());
        self::assertFalse($cancelled->hasFailed());
        self::assertTrue($failed->hasFailed());
        self::assertSame('Saldo insuficiente.', $failed->message());
    }

    public function testItRejectsMalformedProcessorResults(): void
    {
        $this->expectException(InvalidResponseException::class);

        Vinti4Response::fromProcessorResult(['success' => true]);
    }

    private function response(): Vinti4Response
    {
        return Vinti4Response::fromProcessorResult([
            'success' => true,
            'fingerprint_valid' => true,
            'data' => [
                'messageType' => '8',
                'transactionCode' => '1',
                'merchantRespTid' => 'TX123456',
                'merchantRespCP' => '2609',
                'merchantRespMerchantRef' => 'PURCHASE-001',
                'merchantRespMerchantSession' => 'SESSION-001',
                'merchantRespPurchaseAmount' => '1500',
                'merchantRespCurrency' => '132',
                'merchantRespPan' => '1234567890123456',
                'merchantRespDCCData' => json_encode([
                    'dcc' => 'Y',
                    'dccAmount' => '15.50',
                    'dccCurrency' => 'EUR',
                    'dccMarkup' => '3.5',
                    'dccRate' => '0.0103',
                ]),
            ],
        ]);
    }
}
