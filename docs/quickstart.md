# Guia rápido

## 1. Criar o cliente

```php
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);
```

## 2. Preparar a compra

```php
use Eril\Sisp\Billing;

$reference = 'PEDIDO-12345';

$payment = $vinti4->purchase(
    amount: 1500,
    reference: $reference,
    billing: Billing::make()
        ->email('cliente@exemplo.cv')
        ->country('132')
        ->city('Praia')
        ->address('Achada Santo António')
        ->postalCode('7600'),
    currency: 'CVE',
    session: session_id() ?: null,
);
```

Guarde a referência, o valor e a moeda como uma operação pendente antes de abrir o gateway.

## 3. Abrir a página de pagamento

```php
echo $payment->form(
    returnUrl: 'https://exemplo.cv/pagamento/callback',
    lang: 'pt',
);
```

Ou envie e encerre a execução:

```php
$payment->send('https://exemplo.cv/pagamento/callback', 'pt');
```

## 4. Processar o retorno

```php
$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isSuccess()) {
    $reference = $response->merchantReference();
    $transactionId = $response->transactionId();

    // Compare os dados e confirme de forma idempotente.
}
```

Nunca confirme uma operação somente porque o navegador regressou ao seu site.
