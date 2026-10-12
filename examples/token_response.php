<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

// Local HTTP supports manual tests; SISP cross-site returns need HTTPS + SameSite=None.
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_start(['cookie_secure' => $secure, 'cookie_httponly' => true, 'cookie_samesite' => $secure ? 'None' : 'Lax']);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

try {
    $client = new Vinti4Net(
        (string) (getenv('VINTI4_POS_ID') ?: ($_ENV['VINTI4_POS_ID'] ?? '90000045')),
        (string) (getenv('VINTI4_AUTH_CODE') ?: ($_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44')),
    );
    $reference = (string) ($_POST['merchantRespMerchantRef'] ?? $_POST['merchantRef'] ?? '');
    $pending = $_SESSION['token_requests'][$reference] ?? null;
    if (!is_array($pending)) {
        throw new Vinti4Exception('Pedido não encontrado. Verifique a sessão HTTPS ou use os pedidos persistidos na aplicação.');
    }
    $response = $client->processTokenResponse($_POST, $pending['code']);
    $merchantSession = (string) ($response->data['merchantRespMerchantSession'] ?? $response->data['merchantSession'] ?? '');
    if (!hash_equals($pending['session'], $merchantSession)) {
        throw new Vinti4Exception('A sessão recebida não corresponde ao pedido.');
    }
    if ($response->hasInvalidFingerprint()) {
        http_response_code(400);
        echo '<h2>Não foi possível validar a assinatura da resposta.</h2>';
    } elseif ($response->isSuccess()) {
        $title = match ($pending['code']) {
            '5' => 'Token criado', '7' => 'Token cancelado', default => 'Pagamento com token aprovado',
        };
        echo '<h2>' . $escape($title) . '</h2>';
        if ($pending['code'] === '5') {
            echo '<p>Token: <code>' . $escape($response->getToken() ?? '') . '</code></p>';
            echo '<p>Descrição: ' . $escape($response->getTokenDescription() ?? '') . '</p>';
            foreach ($response->getTokenLimits() as $name => $value) {
                echo '<p>' . $escape($name) . ': ' . $escape($value) . '</p>';
            }
        }
        // Persist token/consent or update order status idempotently in the application.
        unset($_SESSION['token_requests'][$reference]);
    } elseif ($response->isCancelled()) {
        echo '<h2>Operação interrompida pelo utilizador.</h2>';
    } else {
        echo '<h2>Operação não aprovada.</h2><p>' . $escape($response->message) . '</p>';
    }
} catch (Vinti4Exception $exception) {
    http_response_code(400);
    echo '<h2>Não foi possível processar a resposta.</h2><p>' . $escape($exception->getMessage()) . '</p>';
}
echo '<p><a href="token_example.php">Voltar à tokenização</a></p>';
