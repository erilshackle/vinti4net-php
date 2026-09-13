<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '90000045';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44';
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;
$callbackUrl = $_ENV['VINTI4_PAYMENT_CALLBACK_URL'] ?? 'http://localhost:8000/payment_response.php';

try {
    $entity = (string) ($_POST['entity'] ?? '');
    $number = (string) ($_POST['number'] ?? '');
    if (!preg_match('/^\d+$/', $entity) || !preg_match('/^\d{1,9}$/', $number)) {
        throw new Vinti4Exception('Entidade ou número de recarga inválido.');
    }

    $vinti4 = new Vinti4Net($posId, $authCode, $endpoint);
    $vinti4->prepareRecharge((string) ($_POST['amount'] ?? ''), (int) $entity, $number);
    $vinti4->setMerchant(Vinti4Net::generateMerchantRef());
    echo $vinti4->createPaymentForm($callbackUrl, 'pt');
} catch (Vinti4Exception $exception) {
    http_response_code(422);
    error_log('Vinti4Net recharge example: ' . $exception->getMessage());
    echo '<h2>Não foi possível iniciar a recarga.</h2><p><a href="/">Voltar</a></p>';
}
