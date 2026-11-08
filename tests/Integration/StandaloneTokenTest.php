<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../dist/vinti4net-standalone.php';

final class StandaloneTokenTest extends TestCase
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

    public function testTokenConvenienceMethodsGenerateFormsForAllOperations(): void
    {
        $client = new \Vinti4Net('90000001', 'auth');
        $callback = 'https://merchant.example/callback';
        $cases = [
            ['5', static fn(\Vinti4Net $client) => $client->prepareTokenRequest('100'), '/TokenEnrollment?'],
            ['6', static fn(\Vinti4Net $client) => $client->prepareTokenPurchase('000123', '100'), '/TokenPayment?'],
            ['2', static fn(\Vinti4Net $client) => $client->prepareTokenServicePayment('000123', '100', 6, '123456'), '/TokenPayment?'],
            ['3', static fn(\Vinti4Net $client) => $client->prepareTokenRecharge('000123', '100', 2, '1234567'), '/TokenPayment?'],
            ['7', static fn(\Vinti4Net $client) => $client->prepareTokenCancellation('000123'), '/TokenCancel?'],
        ];

        foreach ($cases as [$code, $prepare, $route]) {
            $html = $prepare($client)->createPaymentForm($callback);
            self::assertStringContainsString($route, $html);
            self::assertStringContainsString('name="transactionCode" value="' . $code . '"', $html);
            self::assertStringContainsString('name="currency" value="132"', $html);
            self::assertStringContainsString('name="token" value="', $html);
        }
    }

    public function testTokenCallbackVectorsAreValidatedAndPanIsMasked(): void
    {
        $client = new \Vinti4Net($this->fixture['posID'], $this->fixture['posAuthCode']);
        $cases = [
            [$this->fixture['responses'][0], '5'],
            [$this->fixture['responses'][1], '6'],
            [$this->fixture['responses'][2], '7'],
            [$this->fixture['responses'][3], '2'],
            [$this->fixture['responses'][4], '3'],
        ];

        foreach ($cases as [$callback, $code]) {
            self::assertTrue($client->processTokenResponse($callback, $code)['success']);
        }

        $enrollment = $client->processTokenResponse($this->fixture['responses'][0], '5');
        self::assertSame('000123456789', $enrollment['data']['token']);
        self::assertSame('•••• 1234', $enrollment['data']['merchantRespPan']);
        self::assertFalse($client->processTokenResponse($this->fixture['responses'][0], '6')['success']);

        $tampered = $this->fixture['responses'][0];
        $tampered['tokenDescription'] = 'altered';
        self::assertFalse($client->processTokenResponse($tampered, '5')['fingerprint_valid']);
    }

    public function testTokenCallbackUrlAllowsLoopbackButRequiresHttpsOtherwise(): void
    {
        (new \Vinti4Net('POS', 'AUTH'))
            ->prepareTokenRequest('100')
            ->createPaymentForm('http://localhost:8000/callback');

        $this->expectException(\Vinti4Exception::class);
        (new \Vinti4Net('POS', 'AUTH'))
            ->prepareTokenRequest('100')
            ->createPaymentForm('http://merchant.example/callback');
    }

    public function testStandaloneKeepsCurrencyAndCountryAliases(): void
    {
        $currencies = [
            'CVE' => '132',
            'USD' => '840',
            'EUR' => '978',
            'BRL' => '986',
            'GBP' => '826',
            'JPY' => '392',
        ];
        foreach ($currencies as $name => $code) {
            $html = (new \Vinti4Net('POS', 'AUTH'))
                ->preparePurchase('100', [], $name)
                ->createPaymentForm('https://merchant.example/callback');
            self::assertStringContainsString('name="currency" value="' . $code . '"', $html);
        }

        $billing = [
            'email' => 'buyer@example.cv',
            'country' => 'cv',
            'city' => 'Praia',
            'address' => 'Rua SISP',
        ];
        $html = (new \Vinti4Net('POS', 'AUTH'))
            ->preparePurchase('100', $billing)
            ->createPaymentForm('https://merchant.example/callback');
        self::assertSame(1, preg_match('/name="purchaseRequest" value="([^"]+)"/', $html, $match));
        $purchaseRequest = json_decode(
            base64_decode(html_entity_decode($match[1], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
        self::assertSame('132', $purchaseRequest['billAddrCountry']);
    }
}
