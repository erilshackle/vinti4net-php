<?php

declare(strict_types=1);

namespace Tests\Integration;

use Erilshk\Sisp\Vinti4Net;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../dist/standalone.php';

final class StatusSecurityTest extends TestCase
{
    private function assertStatus(string $expected, array $payload): void
    {
        $result = (new Vinti4Net('POS', 'AUTH'))->processResponse($payload);
        $standalone = (new \Vinti4Net('POS', 'AUTH'))->processResponse($payload);
        self::assertSame($expected, $result->status);
        self::assertSame($expected, $standalone['status']);
        self::assertSame($expected === 'SUCCESS', $result->isSuccess());
        self::assertSame($expected === 'SUCCESS', $standalone['success']);
    }

    public function testCancellationHasNoFingerprint(): void
    {
        $this->assertStatus('CANCELLED', [
            'merchantRef' => 'R20261003214916',
            'merchantSession' => 'S20261003214916',
            'UserCancelled' => 'true',
        ]);
    }

    public function testSignedErrorAndTampering(): void
    {
        $payload = [
            'messageType' => '6',
            'merchantRespMessageID' => 'jRgpKy0F4H6Z6vaS0SO6',
            'merchantRespErrorCode' => 'F',
            'merchantRespErrorDetail' => 'FALHA NA AUTENTICACAO CLIENTE',
            'merchantRespErrorDescription' => 'FALHA NA AUTENTICACAO CLIENTE',
            'merchantRespMerchantRef' => 'R20261003214916',
            'merchantRespMerchantSession' => 'S20261003214916',
            'merchantRespAdditionalErrorMessage' => 'FALHA NA AUTENTICACAO CLIENTE',
            'merchantRespTimeStamp' => '2026-10-03 21:49:20',
        ];
        $message = base64_encode(hash('sha512', 'AUTH', true))
            . '6jRgpKy0F4H6Z6vaS0SO6F'
            . str_repeat('FALHA NA AUTENTICACAO CLIENTE', 2)
            . 'R20261003214916S20261003214916'
            . 'FALHA NA AUTENTICACAO CLIENTE2026-10-03 21:49:20';
        $payload['resultFingerPrint'] = base64_encode(hash('sha512', $message, true));
        $this->assertStatus('ERROR', $payload);
        $payload['UserCancelled'] = 'true';
        $this->assertStatus('ERROR', $payload);
        $payload['merchantRespErrorDescription'] = 'forged';
        $this->assertStatus('INVALID_FINGERPRINT', $payload);
        unset($payload['resultFingerPrint']);
        $this->assertStatus('INVALID_FINGERPRINT', $payload);
    }

    public function testCancellationFlagCannotOverrideSignedSuccess(): void
    {
        foreach (['8', 'P', 'M', '10'] as $type) {
            $payload = [
                'messageType' => $type,
                'merchantResp' => 'C',
                'merchantRespPurchaseAmount' => '1000',
                'UserCancelled' => 'true',
                'merchantRef' => 'R20261003214916',
                'merchantSession' => 'S20261003214916',
            ];
            $message = base64_encode(hash('sha512', 'AUTH', true)) . $type . '1000000C';
            $payload['resultFingerPrint'] = base64_encode(hash('sha512', $message, true));
            $this->assertStatus('SUCCESS', $payload);
            unset($payload['resultFingerPrint']);
            $this->assertStatus('INVALID_FINGERPRINT', $payload);
        }
    }
}
