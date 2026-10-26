<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../dist/SispVinti4Net.php';

final class SispVinti4NetTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $fixture;

    protected function setUp(): void
    {
        $this->fixture = json_decode(
            file_get_contents(__DIR__ . '/../Fixtures/token-v1.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    public function testSispBillingAndCurrencyAreNotTranslated(): void
    {
        $client = new \SispVinti4Net('90000001', 'TOKEN_TEST_AUTH');
        $billing = [
            'email' => 'buyer@example.cv',
            'billAddrCountry' => '840',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua SISP',
            'billAddrPostCode' => '7600',
            'shipAddrCountry' => '840',
        ];

        $client->preparePurchase('1500', $billing, '840');
        $client->createPaymentForm('https://merchant.example/callback');
        $fields = $client->getPreparedFields();
        self::assertIsArray($fields);
        self::assertSame('840', $fields['currency']);
        self::assertSame($billing, json_decode(base64_decode($fields['purchaseRequest']), true, flags: JSON_THROW_ON_ERROR));
    }

    public function testFriendlyCountryAliasIsNotAcceptedAsABillingField(): void
    {
        $client = new \SispVinti4Net('90000001', 'auth');
        $client->preparePurchase('1500', [
            'email' => 'buyer@example.cv',
            'country' => 'CV',
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Rua SISP',
            'billAddrPostCode' => '7600',
        ]);

        $this->expectException(\SispVinti4NetException::class);
        $client->createPaymentForm('https://merchant.example/callback');
    }

    public function testTokenRequestFingerprintsAndRoutesMatchProjectVectors(): void
    {
        $client = new \SispVinti4Net($this->fixture['posID'], $this->fixture['posAuthCode']);
        foreach ($this->fixture['requests'] as $request) {
            $client->preparePayment($request);
            $client->createPaymentForm($request['urlMerchantResponse']);
            $fields = $client->getPreparedFields();

            self::assertIsArray($fields);
            self::assertSame($request['transactionCode'], $fields['transactionCode']);
            self::assertSame($request['token'], $fields['token']);
            self::assertSame($request['fingerprint'], $fields['fingerprint']);

            $path = match ($request['transactionCode']) {
                '5' => '/TokenEnrollment?',
                '7' => '/TokenCancel?',
                default => '/TokenPayment?',
            };
            self::assertStringContainsString($path, $client->createPaymentForm($request['urlMerchantResponse']));
        }
    }

    public function testTokenConvenienceMethodsCoverEnrollmentPaymentAndCancellation(): void
    {
        $client = new \SispVinti4Net('90000001', 'auth');
        $cases = ['5', '6', '2', '3', '7'];
        foreach ($cases as $code) {
            match ($code) {
                '5' => $client->prepareTokenRequest('100'),
                '6' => $client->prepareTokenPurchase('000123', '100'),
                '2' => $client->prepareTokenServicePayment('000123', '100', '00006', '000123456'),
                '3' => $client->prepareTokenRecharge('000123', '100', '00002', '001234567'),
                '7' => $client->prepareTokenCancellation('000123'),
            };
            $client->createPaymentForm('https://merchant.example/callback');
            $fields = $client->getPreparedFields();
            self::assertIsArray($fields);
            self::assertSame($code, $fields['transactionCode']);
            self::assertSame('132', $fields['currency']);
            if ($code === '7') {
                self::assertSame('0', $fields['amount']);
            }
        }
    }

    public function testTokenCallbacksValidateAgainstPersistedOperationCode(): void
    {
        $client = new \SispVinti4Net($this->fixture['posID'], $this->fixture['posAuthCode']);
        $cases = [
            [$this->fixture['responses'][0], '5'],
            [$this->fixture['responses'][1], '6'],
            [$this->fixture['responses'][2], '7'],
        ];
        foreach ($cases as [$callback, $code]) {
            self::assertTrue($client->processTokenResponse($callback, $code)['success']);
        }
        self::assertFalse($client->processTokenResponse($this->fixture['responses'][0], '6')['success']);
    }
}
