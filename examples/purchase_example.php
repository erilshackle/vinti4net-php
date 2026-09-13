<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '90000045';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44';
// For a test through the 3DS middleware, set VINTI4_ENDPOINT to Sisp::DEFAULT_3DS_SERVER_URL.
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;
$callbackUrl = $_ENV['VINTI4_PAYMENT_CALLBACK_URL'] ?? 'http://localhost:8000/payment_response.php';

try {
    $vinti4 = new Vinti4Net($posId, $authCode, $endpoint);
    $amount = (string) ($_POST['amount'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));

    // The public v2.2 API currently requires Billing. Use real customer details here.
    $billing = Billing::from([
        'email' => $email,
        'country' => '132',
        'city' => 'Praia',
        'address' => 'Avenida Cidade de Lisboa',
        'postalCode' => '7600',
    ]);

    $vinti4->preparePurchase($amount, $billing);
    $vinti4->setMerchant(Vinti4Net::generateMerchantRef());
    echo $vinti4->createPaymentForm($callbackUrl, 'pt');
} catch (Vinti4Exception $exception) {
    http_response_code(422);
    error_log('Vinti4Net purchase example: ' . $exception->getMessage());
    echo '<h2>Não foi possível iniciar a compra.</h2> ' . $exception->getMessage() . ' <p><a href="/">Voltar</a></p>';
}
