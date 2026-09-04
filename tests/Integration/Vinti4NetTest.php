<?php

declare(strict_types=1);

namespace Eril\Sisp\Tests\Integration;

use Eril\Sisp\Exception\InvalidConfigurationException;
use Eril\Sisp\Exception\InvalidRequestException;
use Eril\Sisp\Exception\InvalidResponseException;
use Eril\Sisp\Vinti4Net;
use PHPUnit\Framework\TestCase;

final class Vinti4NetTest extends TestCase
{
    private Vinti4Net $vinti4;

    protected function setUp(): void
    {
        $this->vinti4 = new Vinti4Net(
            'TEST_POS',
            'TEST_AUTH',
            'https://provider.test/payment',
        );
    }

    public function testItGeneratesAPurchaseFormWithNormalizedBilling(): void
    {
        $form = $this->vinti4->purchase(
            amount: 1500,
            reference: 'PURCHASE-001',
            billing: [
                'email' => 'customer@example.com',
                'country' => '132',
                'city' => 'Praia',
                'address' => 'Rua Principal, 1',
                'postalCode' => '7600',
                'mobilePhone' => '9911234',
            ],
            session: 'SESSION-001',
        )->form('https://merchant.test/callback');

        self::assertStringContainsString('method="post"', $form);
        self::assertSame('1', $this->field($form, 'transactionCode'));
        self::assertSame('1500', $this->field($form, 'amount'));
        self::assertSame('PURCHASE-001', $this->field($form, 'merchantRef'));
        self::assertSame('SESSION-001', $this->field($form, 'merchantSession'));

        $billing = json_decode(
            base64_decode($this->field($form, 'purchaseRequest'), true),
            true,
        );

        self::assertSame('customer@example.com', $billing['email']);
        self::assertSame('9911234', $billing['mobilePhone']['subscriber']);
    }

    public function testItGeneratesServiceRechargeAndRefundForms(): void
    {
        $service = $this->vinti4->servicePayment(
            2500,
            10001,
            '123456789',
            'SERVICE-001',
            'SESSION-001',
        )->form('https://merchant.test/callback');

        $recharge = $this->vinti4->recharge(
            500,
            10021,
            '9912345',
            'RECHARGE-001',
            'SESSION-001',
        )->form('https://merchant.test/callback');

        $refund = $this->vinti4->refund(
            1500,
            'TX123456',
            '2609',
            'REFUND-001',
            'SESSION-001',
        )->form('https://merchant.test/callback');

        self::assertSame('2', $this->field($service, 'transactionCode'));
        self::assertSame('10001', $this->field($service, 'entityCode'));
        self::assertSame('3', $this->field($recharge, 'transactionCode'));
        self::assertSame('9912345', $this->field($recharge, 'referenceNumber'));
        self::assertSame('4', $this->field($refund, 'transactionCode'));
        self::assertSame('TX123456', $this->field($refund, 'transactionID'));
        self::assertSame('2609', $this->field($refund, 'clearingPeriod'));
        self::assertSame('R', $this->field($refund, 'reversal'));
    }

    public function testItRejectsInvalidConfiguration(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        new Vinti4Net('', 'TEST_AUTH');
    }

    public function testItRejectsInvalidTransactionData(): void
    {
        $this->expectException(InvalidRequestException::class);

        $this->vinti4->servicePayment(
            0,
            10001,
            '123456789',
            'SERVICE-001',
        )->form('https://merchant.test/callback');
    }

    public function testItRejectsAnEmptyResponse(): void
    {
        $this->expectException(InvalidResponseException::class);

        $this->vinti4->processResponse([]);
    }

    private function field(string $html, string $name): string
    {
        $pattern = sprintf(
            '/name="%s" value="([^"]*)"/',
            preg_quote($name, '/'),
        );

        self::assertSame(1, preg_match($pattern, $html, $matches));

        return html_entity_decode(
            $matches[1],
            ENT_QUOTES | ENT_HTML5,
            'UTF-8',
        );
    }
}
