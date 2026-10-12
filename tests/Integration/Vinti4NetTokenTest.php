<?php

declare(strict_types=1);

namespace Tests\Integration;

use Erilshk\Sisp\Vinti4Net;
use PHPUnit\Framework\TestCase;

class Vinti4NetTokenTest extends TestCase
{
    public function testCustomEndpointAndVariableMerchantIdentifiers(): void
    {
        $client = new Vinti4Net('90000001', 'auth', 'https://custom.example/transaction?tenant=demo');
        foreach (['order-42', str_repeat('R', 30)] as $reference) {
            $client->setMerchant($reference, 'session-42');
            foreach ([['prepareTokenRequest', ['100']],
                ['prepareTokenPurchase', ['000123', '100']],
                ['prepareTokenCancellation', ['000123']]] as [$method, $args]) {
                $client->$method(...$args);
                $form = $client->createPaymentForm('https://merchant.example/callback');
                self::assertStringContainsString('https://custom.example/transaction?tenant=demo&amp;FingerPrint=', $form);
                self::assertStringContainsString("name='merchantRef' value='{$reference}'", $form);
                self::assertStringContainsString("name='merchantSession' value='session-42'", $form);
            }
        }
        self::assertSame(15, strlen(Vinti4Net::generateMerchantRef()));
        self::assertSame(15, strlen(Vinti4Net::generateMerchantRef(true)));
        self::assertCount(3, (new \ReflectionMethod(Vinti4Net::class, '__construct'))->getParameters());
        self::assertFalse(method_exists($client, 'setTokenEndpoints'));
    }

    public function testFacadeFormsAndReuseDoNotLeakTokenToLegacyPayments(): void
    {
        $client = new Vinti4Net('90000001', 'auth');
        $client->setMerchant('R20210714100158', 'S26101210300012');
        $operations = [
            ['prepareTokenRequest', ['89'], '5', 'TokenEnrollment'],
            ['prepareTokenPurchase', ['000123', '89'], '6', 'TokenPayment'],
            ['prepareTokenServicePayment', ['000123', '89', 6, '000123456'], '2', 'TokenPayment'],
            ['prepareTokenRecharge', ['000123', '89', 2, '9573234'], '3', 'TokenPayment'],
            ['prepareTokenCancellation', ['000123'], '7', 'TokenCancel'],
        ];
        foreach ($operations as [$method, $args, $code, $path]) {
            $client->$method(...$args);
            $form = $client->createPaymentForm('https://example.com/callback');
            self::assertStringContainsString('https://mc.vinti4net.cv/BizMPIOnUsSisp/' . $path . '?', $form);
            self::assertStringContainsString("name='transactionCode' value='{$code}'", $form);
            self::assertStringContainsString("name='fingerprintversion' value='1'", $form);
            self::assertStringNotContainsString('purchaseRequest', $form);
            if ($code !== '5') self::assertStringContainsString("name='token' value='000123'", $form);
        }
        $client->prepareServicePayment('89', 6, '123456789');
        $form = $client->createPaymentForm('https://example.com/callback');
        self::assertStringContainsString('https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment?', $form);
        self::assertStringNotContainsString("name='token'", $form);
        $client->prepareTokenPurchase("000'<token>", '89');
        $form = $client->createPaymentForm('https://example.com/callback');
        self::assertStringContainsString('000&#039;&lt;token&gt;', $form);
    }

    public function testFacadeDispatchAndResponseAccessors(): void
    {
        $fixtures = json_decode(file_get_contents(__DIR__ . '/../Fixtures/token-v1.json'), true, flags: JSON_THROW_ON_ERROR);
        $client = new Vinti4Net('90000001', 'TOKEN_TEST_AUTH');
        foreach ($fixtures['responses'] as $data) {
            $response = $client->processResponse($data);
            self::assertTrue($response->isSuccess());
            self::assertSame($data, $response->data);
            self::assertFalse($response->isCancelled());
        }
        $response = $client->processTokenResponse($fixtures['responses'][0], '5');
        self::assertSame('000123456789', $response->getToken());
        self::assertSame('Token de teste', $response->getTokenDescription());
        self::assertSame('2030-12-31', $response->getTokenLimits()['limitDate']);
        self::assertFalse($client->processTokenResponse($fixtures['responses'][0], '7')->isSuccess());
        self::assertTrue($client->processTokenResponse([
            'merchantRef' => 'R20210714100158', 'merchantSession' => 'S26101210300012', 'userCancelled' => 'true',
        ])->isCancelled());
    }
}
