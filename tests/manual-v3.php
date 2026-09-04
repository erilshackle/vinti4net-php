<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

use Eril\Sisp\Vinti4Net;

/**
 * Assert that a generated form contains an expected value.
 */
function contains(string $html, string $expected): void
{
    if (!str_contains($html, $expected)) {
        throw new RuntimeException(
            "Conteúdo não encontrado no formulário: {$expected}"
        );
    }
}

/**
 * Print a successful test result.
 */
function passed(string $operation): void
{
    echo "[OK] {$operation}\n";
}

$vinti4 = new Vinti4Net(
    posId: 'TEST_POS',
    authCode: 'TEST_AUTH_CODE',
    endpoint: 'https://provider.test/payment',
);

$returnUrl = 'https://merchant.test/payment/callback';

/*
|--------------------------------------------------------------------------
| Purchase
|--------------------------------------------------------------------------
*/

$purchase = $vinti4->purchase(
    amount: 1500,
    reference: 'PURCHASE-0001',
    billing: [
        'email' => 'customer@example.com',
        'country' => '132',
        'city' => 'Praia',
        'address' => 'Achada Santo António',
        'postalCode' => '7600',
    ],
);

$purchaseForm = $purchase->form($returnUrl);

contains($purchaseForm, 'method="post"');
contains($purchaseForm, 'https://provider.test/payment');
contains($purchaseForm, 'name="merchantRef" value="PURCHASE-0001"');
contains($purchaseForm, 'name="amount" value="1500"');
contains($purchaseForm, 'name="transactionCode" value="1"');
contains($purchaseForm, 'name="purchaseRequest"');
contains($purchaseForm, 'name="fingerprint"');
contains($purchaseForm, 'name="urlMerchantResponse"');

passed('Purchase');

/*
|--------------------------------------------------------------------------
| Service payment
|--------------------------------------------------------------------------
*/

$service = $vinti4->servicePayment(
    amount: 2500,
    entity: 10001,
    number: '123456789',
    reference: 'SERVICE-0001',
);

$serviceForm = $service->form($returnUrl);

contains($serviceForm, 'name="merchantRef" value="SERVICE-0001"');
contains($serviceForm, 'name="amount" value="2500"');
contains($serviceForm, 'name="transactionCode" value="2"');
contains($serviceForm, 'name="entityCode" value="10001"');
contains($serviceForm, 'name="referenceNumber" value="123456789"');
contains($serviceForm, 'name="fingerprint"');

passed('Service payment');

/*
|--------------------------------------------------------------------------
| Recharge
|--------------------------------------------------------------------------
*/

$recharge = $vinti4->recharge(
    amount: 500,
    entity: 10021,
    number: '9912345',
    reference: 'RECHARGE-0001',
);

$rechargeForm = $recharge->form(
    returnUrl: $returnUrl,
    lang: 'pt',
);

contains($rechargeForm, 'name="merchantRef" value="RECHARGE-0001"');
contains($rechargeForm, 'name="amount" value="500"');
contains($rechargeForm, 'name="transactionCode" value="3"');
contains($rechargeForm, 'name="entityCode" value="10021"');
contains($rechargeForm, 'name="referenceNumber" value="9912345"');
contains($rechargeForm, 'name="fingerprint"');

passed('Recharge');

/*
|--------------------------------------------------------------------------
| Refund
|--------------------------------------------------------------------------
*/

$refund = $vinti4->refund(
    amount: 1500,
    transactionId: 'TX123456',
    clearingPeriod: '2609',
    reference: 'REFUND-0001',
);

$refundForm = $refund->form($returnUrl);

contains($refundForm, 'name="merchantRef" value="REFUND-0001"');
contains($refundForm, 'name="amount" value="1500"');
contains($refundForm, 'name="transactionCode" value="4"');
contains($refundForm, 'name="transactionID" value="TX123456"');
contains($refundForm, 'name="clearingPeriod" value="2609"');
contains($refundForm, 'name="reversal" value="R"');
contains($refundForm, 'name="fingerprint"');

passed('Refund');

echo "\nTodos os formulários foram gerados com sucesso.\n";