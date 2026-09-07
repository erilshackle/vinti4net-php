<?php

declare(strict_types=1);

/**
 * Exemplo de processamento da resposta da Vinti4Net.
 *
 * Este arquivo recebe o callback da SISP, valida a resposta
 * e apresenta o resultado da transação.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? '';
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;

try {
    $vinti4 = new Vinti4Net(
        posID: $posId,
        posAuthCode: $authCode,
        endpoint: $endpoint
    );

    $response = $vinti4->processResponse($_POST);

    if ($response->hasInvalidFingerprint()) {
        error_log('Vinti4Net: callback com fingerprint inválido.');

        http_response_code(400);
        exit('Não foi possível validar a resposta recebida.');
    }

    http_response_code(200);

    if ($response->isSuccess()) {
        $transactionId = $response->getTransactionId();
        $merchantRef = $response->getMerchantRef();
        $amount = $response->getAmount();

        /*
         * Antes de concluir o pedido:
         *
         * - confirme que $merchantRef pertence a um pedido válido;
         * - compare $amount com o valor guardado;
         * - registe $transactionId;
         * - processe o pedido de forma idempotente.
         */

        echo ($response->dcc['enabled'] ?? false)
            ? $response->renderDccReceipt()
            : $response->renderReceipt(null, [
                'companyName' => 'Minha Loja',
            ]);
    } elseif ($response->isCancelled()) {
        echo '<h2>Pagamento cancelado</h2>';
        echo '<p>O pagamento foi cancelado antes da conclusão.</p>';
    } else {
        echo '<h2>Pagamento não aprovado</h2>';
        echo '<p>A transação não foi concluída.</p>';
    }
} catch (Vinti4Exception $exception) {
    error_log('Vinti4Net: ' . $exception->getMessage());

    http_response_code(400);

    echo '<h2>Não foi possível processar a resposta</h2>';
    echo '<p>Verifique os dados recebidos e tente novamente.</p>';
}

?>

<p><a href="/">Voltar ao exemplo</a></p>