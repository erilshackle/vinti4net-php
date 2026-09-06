<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? '';
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;
$callbackUrl = $_ENV['VINTI4_CALLBACK_URL'] ?? '';

try {

    $vinti4 = new Vinti4Net(
        posID: $posId,          // Fornecido pelo SISP
        posAuthCode: $authCode, // Fornecido pelo SISP
        endpoint: $endpoint     // Use null para produção
    );
    

    $response = $vinti4->processResponse($_POST);

    if ($response->hasInvalidFingerprint()) {
        error_log('Vinti4Net: callback com fingerprint inválido.');
        http_response_code(400);
        exit('Resposta inválida.');
    }

    if ($response->isSuccess()) {
        $transactionId = $response->getTransactionId();
        $merchantRef = $response->getMerchantRef();
        $amount = $response->getAmount();

        // Compare referência e valor com o pedido guardado.
        // Registe $transactionId e conclua o pedido de forma idempotente.

        echo ($response->dcc['enabled'] ?? false)
            ? $response->renderDccReceipt()
            : $response->renderReceipt(data: ['companyName' => 'Minha Loja']);
    } elseif ($response->isCancelled()) {
        echo 'Pagamento cancelado.';
    } else {
        echo 'Pagamento não aprovado.';
    }

    http_response_code(200);
} catch (Vinti4Exception $exception) {
    print('Vinti4Net: ' . $exception->getMessage());
    http_response_code(400);
    echo 'Não foi possível processar a resposta.';
}

?>
<br>
<a href="/">voltar</a>
