# Guia rápido

Este exemplo cria uma compra 3DS e processa o retorno.

## Criar o pagamento

```php
<?php

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    $_ENV['VINTI4_POS_ID'],
    $_ENV['VINTI4_AUTH_CODE'],
);

$billing = Billing::from([
    'email' => 'cliente@example.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Avenida Cidade de Lisboa',
    'postalCode' => '7600',
]);

try {
    $vinti4
        ->setMerchant('PEDIDO00000001')
        ->preparePurchase(1500, $billing);

    echo $vinti4->createPaymentForm(
        'https://loja.example.cv/pagamentos/vinti4/callback',
        'pt',
    );
} catch (Vinti4Exception $exception) {
    http_response_code(422);
    echo htmlspecialchars($exception->getMessage());
}
```

O HTML retornado contém um formulário auto-submit. Não altere seus campos.

## Processar o callback

```php
<?php

$vinti4 = new Vinti4Net(
    $_ENV['VINTI4_POS_ID'],
    $_ENV['VINTI4_AUTH_CODE'],
);

$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isCancelled()) {
    exit('Pagamento cancelado.');
}

if (!$response->isSuccess()) {
    http_response_code(422);
    exit($response->message);
}

$reference = $response->getMerchantRef();
$transactionId = $response->getTransactionId();
$clearingPeriod = $response->getClearingPeriod();
$amount = $response->getAmount();

// Localize o pedido, compare os dados e confirme uma única vez.

echo $response->renderReceipt(data: [
    'companyName' => 'Minha Empresa, Lda.',
]);
```

Nunca confirme um pedido usando somente a referência recebida.

