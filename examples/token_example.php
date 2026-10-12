<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Core\Sisp;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

// Local HTTP supports manual tests; SISP cross-site returns need HTTPS + SameSite=None.
$secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
session_start(['cookie_secure' => $secure, 'cookie_httponly' => true, 'cookie_samesite' => $secure ? 'None' : 'Lax']);
$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $client = new Vinti4Net(
            (string) (getenv('VINTI4_POS_ID') ?: ($_ENV['VINTI4_POS_ID'] ?? '90000045')),
            (string) (getenv('VINTI4_AUTH_CODE') ?: ($_ENV['VINTI4_AUTH_CODE'] ?? 'kfyhhKJH875ndu44')),
        );
        $callback = (string) (getenv('VINTI4_TOKEN_CALLBACK_URL') ?: ($_ENV['VINTI4_TOKEN_CALLBACK_URL'] ?? 'http://localhost:8000/token_response.php'));
        $code = (string) ($_POST['operation'] ?? '');
        $amount = (string) ($_POST['amount'] ?? '');
        $token = (string) ($_POST['token'] ?? ''); // Preserve leading zeros.
        $entity = (string) ($_POST['entity'] ?? '');
        $number = (string) ($_POST['number'] ?? '');
        if (in_array($code, ['2', '3'], true) && !preg_match('/^\d{1,5}$/', $entity)) {
            throw new Vinti4Exception('A entidade deve ter até 5 dígitos.');
        }
        match ($code) {
            '5' => $client->prepareTokenRequest($amount),
            '6' => $client->prepareTokenPurchase($token, $amount),
            '2' => $client->prepareTokenServicePayment($token, $amount, (int) $entity, $number),
            '3' => $client->prepareTokenRecharge($token, $amount, (int) $entity, $number),
            '7' => $client->prepareTokenCancellation($token),
            default => throw new Vinti4Exception('Operação desconhecida.'),
        };
        $reference = Vinti4Net::generateMerchantRef();
        $merchantSession = Sisp::generateSession();
        $client->setMerchant($reference, $merchantSession);
        $form = $client->createPaymentForm($callback);
        // Demonstration only: persist pending operations in a database in a real application.
        $_SESSION['token_requests'][$reference] = ['code' => $code, 'session' => $merchantSession];
        session_write_close();
        echo $form;
        exit;
    } catch (Vinti4Exception $exception) {
        http_response_code(422);
        $error = $exception->getMessage();
    }
}
?>
<!doctype html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tokenização Vinti4Net</title>
    <style>
        body{font:16px/1.6 system-ui,sans-serif;background:#f8fafc;color:#0f172a;margin:0}
        main{max-width:620px;margin:40px auto;padding:24px;background:white;border:1px solid #e2e8f0;border-radius:12px}
        label{display:block;margin-top:16px;font-weight:600}input,select,button{box-sizing:border-box;width:100%;padding:10px;font:inherit}
        button{margin-top:24px;background:#2563eb;color:white;border:0;border-radius:6px;cursor:pointer}.error{color:#b91c1c}
        [hidden]{display:none}
    </style>
</head>
<body><main>
    <p><a href="index.php">Voltar aos exemplos</a></p>
    <h1>Tokenização Vinti4Net</h1>
    <p>Crie um token, pague com um token existente ou cancele a sua utilização.</p>
    <p>Configure <code>VINTI4_POS_ID</code>, <code>VINTI4_AUTH_CODE</code> e <code>VINTI4_TOKEN_CALLBACK_URL</code>, apontando para <code>token_response.php</code> num endereço HTTPS público. Pode preparar pedidos e simular callbacks em HTTP local. Para testar o retorno real da SISP, use um endereço HTTPS público (por exemplo, um túnel HTTPS), que também permite manter a sessão no POST entre sites.</p>
    <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
    <form method="post">
        <label for="operation">Operação</label>
        <select id="operation" name="operation">
            <option value="5">Criar token</option><option value="6">Compra com token</option>
            <option value="2">Serviço com token</option><option value="3">Recarga com token</option>
            <option value="7">Cancelar token</option>
        </select>
        <div id="amount-fields"><label for="amount" id="amount-label">Montante mínimo de futuros pagamentos (CVE)</label><input id="amount" name="amount" type="number" min="1" step="1" value="100" required></div>
        <div id="token-fields" hidden><label for="token">Token existente</label><input id="token" name="token" type="text" autocomplete="off"></div>
        <div id="service-fields" hidden>
            <label for="entity">Entidade</label><input id="entity" name="entity" inputmode="numeric" pattern="[0-9]{1,5}">
            <label for="number">Referência / telemóvel</label><input id="number" name="number" inputmode="numeric" pattern="[0-9]{1,9}">
        </div>
        <p id="operation-note">O montante indicado não é debitado na criação do token. Os dados do cartão e o OTP são introduzidos na SISP.</p>
        <button type="submit">Continuar na SISP</button>
    </form>
</main>
<script>
    const operation=document.getElementById('operation');
    function updateFields(){
        const enrollment=operation.value==='5',cancel=operation.value==='7',service=['2','3'].includes(operation.value);
        document.getElementById('amount-fields').hidden=cancel;
        document.getElementById('amount').required=!cancel;
        document.getElementById('token-fields').hidden=enrollment;
        document.getElementById('token').required=!enrollment;
        document.getElementById('service-fields').hidden=!service;
        ['entity','number'].forEach(id=>document.getElementById(id).required=service);
        document.getElementById('amount-label').textContent=enrollment?'Montante mínimo de futuros pagamentos (CVE)':'Valor a pagar (CVE)';
        document.getElementById('operation-note').textContent=enrollment?'O montante indicado não é debitado na criação do token. Os dados do cartão e o OTP são introduzidos na SISP.':cancel?'O cancelamento usa montante zero e impede pagamentos futuros com este token.':'O pagamento utiliza o token existente, dentro dos limites autorizados pelo titular.';
    }
    operation.addEventListener('change',updateFields);updateFields();
</script></body></html>
