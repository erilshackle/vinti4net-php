# Processar a resposta

O callback deve criar o cliente com as mesmas credenciais e entregar o POST original à biblioteca.

```php
$vinti4 = new Vinti4Net($posID, $authCode);
$response = $vinti4->processResponse($_POST);
```

Um array vazio lança `Vinti4Exception`.

## Ordem recomendada

```php
if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isCancelled()) {
    exit('Pagamento cancelado.');
}

if ($response->hasFailed()) {
    http_response_code(422);
    exit($response->message);
}

if ($response->isSuccess()) {
    // Validar contra o pedido salvo e confirmar uma única vez.
}
```

## Estados

| Estado | Método | Significado |
| --- | --- | --- |
| `SUCCESS` | `isSuccess()` | Tipo de sucesso e fingerprint válido |
| `CANCELLED` | `isCancelled()` | Utilizador cancelou |
| `ERROR` | `hasFailed()` | Recusa ou erro devolvido pela SISP |
| `INVALID_FINGERPRINT` | `hasInvalidFingerprint()` | Resposta de sucesso com validação inválida |

`hasFailed()` também é verdadeiro para fingerprint inválido. Por isso, verifique `hasInvalidFingerprint()` primeiro.

## Mensagens de erro

A v2.2 procura, nesta ordem:

1. `merchantRespErrorDescription`;
2. `merchantRespErrorDetail`;
3. `merchantRespAdditionalErrorMessage`;
4. mensagem genérica.

```php
echo $response->message;
echo $response->detail ?? '';
```

## Dados principais

```php
$transactionId = $response->getTransactionId();
$clearingPeriod = $response->getClearingPeriod();
$reference = $response->getMerchantRef();
$amount = $response->getAmount();
$currency = $response->getCurrency();
```

Todos podem retornar `null` quando o campo não existir naquele tipo de resposta.

## Array e JSON seguros

```php
$array = $response->toArray();
$json = $response->toJson();
```

Esses métodos mascaram `merchantRespPan`. A propriedade `$response->data` mantém o payload original e deve ser tratada como sensível.

## Exemplo de cancelamento

```php
[
    'merchantRef' => 'PEDIDO00000001',
    'merchantSession' => 'S20260906133152',
    'UserCancelled' => 'true',
]
```

## Exemplo de erro

Uma autenticação recusada permanece em `ERROR` e pode expor uma mensagem como `Unable validate secure password. Please try again later.`. Ela não deve ser apresentada como fingerprint inválido.

