<?php

declare(strict_types=1);

namespace Tests\Integration;

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Core\Payment;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../dist/standalone.php';

final class ThreeDSBillingTest extends TestCase
{
    private function payload(array $billing): array
    {
        $request = (new Payment('POS', 'AUTH'))->preparePayment([
            'transactionCode' => '1', 'amount' => '1500',
            'urlMerchantResponse' => 'https://example.test/callback',
            'billing' => $billing,
        ]);
        return json_decode(base64_decode($request['fields']['purchaseRequest']), true, 512, JSON_THROW_ON_ERROR);
    }

    private function minimal(): array
    {
        return ['email' => 'cliente@example.cv', 'city' => 'Praia', 'address' => 'Rua Principal'];
    }

    public function testShippingAndDefaultsInBothIntegrations(): void
    {
        foreach ([true, 'y', 'Y'] as $matches) {
            $input = $this->minimal() + ['country' => 'CPV', 'addrMatch' => $matches, 'shipCity' => 'ignored'];
            $lib = $this->payload($input);
            $standalone = (new \Vinti4Net('POS', 'AUTH'))->preparePurchase('1500', $input)
                ->createPaymentForm('https://example.test/callback');
            preg_match('/name="purchaseRequest" value="([^"]+)"/', $standalone, $match);
            $other = json_decode(base64_decode(html_entity_decode($match[1])), true, 512, JSON_THROW_ON_ERROR);
            foreach ([$lib, $other] as $data) {
                self::assertSame('CPV', $data['billAddrCountry']);
                self::assertSame('0000', $data['billAddrPostCode']);
                self::assertSame('0000', $data['shipAddrPostCode']);
                self::assertSame('Praia', $data['shipAddrCity']);
                self::assertSame('Rua Principal', $data['billAddrLine2']);
                self::assertSame('Rua Principal', $data['shipAddrLine1']);
            }
        }
    }

    public function testNamedAccountFieldsAndNoInventedIndicators(): void
    {
        $billing = Billing::from($this->minimal())->account(
            id: 'cliente-123', createdAt: '20261001', passwordChangedAt: '20261002',
        )->toArray();
        $data = $this->payload($billing);
        self::assertSame('cliente-123', $data['acctID']);
        self::assertSame('20261001', $data['acctInfo']['chAccDate']);
        self::assertSame('20261002', $data['acctInfo']['chAccPwChange']);
        self::assertArrayNotHasKey('chAccAgeInd', $data['acctInfo']);
        self::assertArrayNotHasKey('chAccPwChangeInd', $data['acctInfo']);
    }

    public function testLegacyCountryAndAccountInformationArePreserved(): void
    {
        $user = $this->minimal() + ['country' => '840', 'created_at' => '2026-10-01', 'updated_at' => '2026-10-02'];
        $data = $this->payload(['user' => $user, 'acctInfo' => ['chAccAgeInd' => '03']]);
        self::assertSame('840', $data['billAddrCountry']);
        self::assertSame('20261001', $data['acctInfo']['chAccDate']);
        self::assertSame('03', $data['acctInfo']['chAccAgeInd']);
        self::assertArrayNotHasKey('chAccPwChange', $data['acctInfo']);
        $user['password_changed_at'] = '2026-10-03';
        self::assertSame('20261003', $this->payload(['user' => $user])['acctInfo']['chAccPwChange']);
    }

    public function testRejectsInvalidEmailAndAccountId(): void
    {
        $cases = [
            ['email' => 'invalid'], ['acctID' => str_repeat('X', 65)],
        ];
        foreach ($cases as $change) {
            try {
                $this->payload(array_replace($this->minimal(), $change));
                self::fail('Invalid billing must be rejected.');
            } catch (Vinti4Exception $exception) {
                self::assertNotSame('', $exception->getMessage());
            }
        }
    }

    public function testOptionalFieldsArePassedThrough(): void
    {
        $input = array_replace($this->minimal(), [
            'address' => str_repeat('a', 80),
            'mobilePhone' => ['cc' => '+238', 'subscriber' => '991 23 45'],
            'acctInfo' => ['chAccAgeInd' => 3],
        ]);
        $data = $this->payload($input);
        self::assertSame($input['address'], $data['billAddrLine1']);
        self::assertSame(['cc' => '238', 'subscriber' => '9912345'], $data['mobilePhone']);
        self::assertSame(3, $data['acctInfo']['chAccAgeInd']);
    }

    public function testNoThreeDSFlagChangeAndExplicitNPreservesShipping(): void
    {
        $input = $this->minimal() + ['addrMatch' => 'n', 'shipCity' => 'Mindelo'];
        self::assertSame('Mindelo', $this->payload($input)['shipAddrCity']);
        $request = (new Payment('POS', 'AUTH'))->preparePayment([
            'transactionCode' => '1', 'amount' => '1500',
            'urlMerchantResponse' => 'https://example.test/callback',
            'billing' => [],
        ]);
        self::assertSame('1', $request['fields']['is3DSec']);
        self::assertArrayNotHasKey('purchaseRequest', $request['fields']);
    }
}
