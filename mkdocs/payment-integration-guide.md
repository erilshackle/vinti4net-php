# Integração completa

## 1. Criar o pedido no seu sistema

Antes de chamar a biblioteca, guarde referência única, sessão, montante, moeda, cliente e estado `pendente`.

```php
$reference = 'PEDIDO00000001';
$session = 'S' . date('YmdHis');
$amount = 1500;
```

## 2. Preparar a transação

```php

$vinti4
    ->setMerchant($reference, $session)
    ->preparePurchase($amount, $billing);
```

Use um método `prepare...()` por envio. Outro `prepare...()` substitui os dados específicos da transação anterior.

## 3. Enviar para a Vinti4

```php
echo $vinti4->createPaymentForm(
    responseUrl: 'https://loja.example.cv/pagamentos/vinti4/callback',
    lang: 'pt',
);
```

O método valida o pedido, normaliza valores, cria os dados 3DS quando necessário, calcula o fingerprint e retorna o formulário auto-submit.

## 4. Receber e classificar o callback

```php
$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isCancelled()) {
    exit('Operação cancelada.');
}

if ($response->hasFailed()) {
    http_response_code(422);
    exit($response->message);
}
```

## 5. Validar contra o seu banco

| Dado | Callback | Seu banco |
| --- | --- | --- |
| Referência | `getMerchantRef()` | referência do pedido |
| Sessão | `data['merchantRespMerchantSession']` | sessão da tentativa |
| Montante | `getAmount()` | total esperado |
| Estado | `isSuccess()` | ainda pendente |

Só confirme a compra quando tudo estiver correto.

## 6. Guardar dados para reembolso

```php
$transactionId = $response->getTransactionId();
$clearingPeriod = $response->getClearingPeriod();
```

## 7. Mostrar o recibo

```php
echo ($response->dcc['enabled'] ?? false)
    ? $response->renderDccReceipt()
    : $response->renderReceipt(data: [
        'companyName' => 'Minha Empresa, Lda.',
    ]);
```

| Estado | Ação recomendada |
| --- | --- |
| `SUCCESS` | Conferir os dados e confirmar uma vez |
| `ERROR` | Mostrar mensagem segura e permitir nova tentativa |
| `CANCELLED` | Não confirmar; permitir voltar ao pedido |
| `INVALID_FINGERPRINT` | Rejeitar e investigar |
