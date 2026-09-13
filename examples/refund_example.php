<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '90000045';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44';
$endpoint = $_ENV['VINTI4_REFUND_ENDPOINT'] ?? null;
$callbackUrl = $_ENV['VINTI4_REFUND_CALLBACK_URL'] ?? 'http://localhost:8000/refund_response.php';

try {
    $vinti4 = new Vinti4Net($posId, $authCode, $endpoint);
    $vinti4->prepareRefund(
        amount: (string) ($_POST['amount'] ?? ''),
        transactionID: (string) ($_POST['transaction_id'] ?? ''),
        clearingPeriod: (string) ($_POST['clearing_period'] ?? ''),
    );
    $vinti4->setMerchant(Vinti4Net::generateMerchantRef());

    // In an application, persist the refund reference, original transaction ID and
    // full amount before submitting; the callback does not return that amount.
    echo $vinti4->createPaymentForm($callbackUrl, 'pt');
} catch (Vinti4Exception $exception) {
    http_response_code(422);
    error_log('Vinti4Net refund example: ' . $exception->getMessage());
    echo '<h2>Não foi possível iniciar o estorno.</h2><p><a href="/">Voltar</a></p>';
}
