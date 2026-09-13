<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$escape = static fn(mixed $value): string => htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $vinti4 = new Vinti4Net($_ENV['VINTI4_POS_ID'] ?? '90000045', $_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44');
    $response = $vinti4->processResponse($_POST);

    if ($response->hasInvalidFingerprint()) {
        http_response_code(400);
        error_log('Vinti4Net: fingerprint inválido no callback de estorno.');
        echo '<h2>Não foi possível validar a resposta.</h2>';
    } elseif ($response->isSuccess()) {
        // Find the refund by getMerchantRef(). Verify its original amount and ID
        // in your database; the SISP refund response reports amount = 0.
        // Then mark it refunded idempotently and optionally render its receipt:
        // echo $response->renderRefundReceipt(
        //     amount: $storedOriginalAmount,
        //     originalTransactionId: $storedOriginalTransactionId,
        //     data: ['companyName' => 'Minha Loja'],
        // );
        echo '<h2>Estorno processado com sucesso</h2>';
        echo '<p>Referência: ' . $escape($response->getMerchantRef()) . '</p>';
        echo '<p>Transação do estorno: ' . $escape($response->getTransactionId()) . '</p>';
    } elseif ($response->isCancelled()) {
        echo '<h2>Operação cancelada</h2>';
    } else {
        echo '<h2>Estorno não aprovado</h2><p>' . $escape($response->message) . '</p>';
    }
} catch (Vinti4Exception $exception) {
    http_response_code(400);
    error_log('Vinti4Net refund callback: ' . $exception->getMessage());
    echo '<h2>Não foi possível processar a resposta.</h2>';
}

echo '<p><a href="/">Voltar ao exemplo</a></p>';
