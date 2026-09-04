<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Eril\Sisp\Exception\Vinti4Exception;
use Eril\Sisp\Vinti4Net;

try {
    $vinti4 = new Vinti4Net(
        posId: $_ENV['SISP_POS_ID'],
        authCode: $_ENV['SISP_AUTH_CODE'],
    );

    $response = $vinti4->processResponse($_POST);

} catch (Vinti4Exception $exception) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

$reference = $response->merchantReference();

// Localize a operação por $reference e rejeite referências desconhecidas.

if ($response->isSuccess()) {
    // Compare amount() e currency() com os dados locais.
    // Guarde transactionId() e clearingPeriod(). podem ser usados no refund
    // Confirme o pagamento apenas uma vez.

    http_response_code(200);
    echo $response->renderDefaultReceipt('Minha Empresa');
    exit;
}

if ($response->isCancelled()) {
    http_response_code(200);
    exit('Pagamento cancelado.');
}

http_response_code(200);
exit('Pagamento não concluído.');
