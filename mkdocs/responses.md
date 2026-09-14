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

`hasFailed()` só é verdadeiro para `ERROR`. Verifique `hasInvalidFingerprint()` separadamente e antes de qualquer lógica de confirmação.

## Mensagens de erro

A mensagem apresentada usa, quando disponível, o primeiro destes campos:

1. `merchantRespAdditionalErrorMessage`;
2. `merchantRespErrorDetail`;
3. `merchantRespErrorDescription`;
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

Em compras aprovadas, o SDK exige a confirmação `merchantResp=C` e um fingerprint válido. Em estornos aprovados, o tipo de resposta é `10`; o campo `merchantResp` não precisa de ser `C`. Um erro de resposta não identifica necessariamente a operação: `operation` fica `null`. Consulte a compra ou o estorno pendente pela referência guardada. Se o fingerprint de uma resposta de sucesso for inválido, `hasInvalidFingerprint()` tem prioridade sobre um sinal de cancelamento.

## Array e JSON seguros

```php
$array = $response->toArray();
$json = $response->toJson();
```

Esses métodos mascaram `merchantRespPan`. A propriedade `$response->data` mantém o payload original e deve ser tratada como sensível.

Para dúvidas sobre erros e cancelamentos, consulte [Perguntas frequentes](faq.md).

