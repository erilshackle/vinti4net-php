# Respostas e callbacks

## Processamento

```php
use Eril\Sisp\Exception\InvalidResponseException;

try {
    $response = $vinti4->processResponse($_POST);
} catch (InvalidResponseException $exception) {
    http_response_code(400);
    exit('Resposta inválida.');
}
```

## Estados

```php
$response->isValid();
$response->isSuccess();
$response->isCancelled();
$response->hasFailed();
$response->hasInvalidFingerprint();
```

Valide primeiro o fingerprint:

```php
if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}
```

## Dados normalizados

```php
$response->status();
$response->message();
$response->detail();
$response->additionalErrorMessage();
$response->merchantReference();
$response->merchantSession();
$response->transactionId();
$response->clearingPeriod();
$response->amount();
$response->currency();
$response->transactionType();
$response->dcc();
```

`toArray()` e `toJson()` não incluem o payload bruto nem o PAN. `raw()` existe para diagnóstico explícito, mas não deve ser registrado em produção.

## Confirmação segura

1. Rejeite fingerprint inválido.
2. Localize a operação pela referência própria.
3. Compare valor e moeda com os dados locais.
4. Confirme apenas quando `isSuccess()` for verdadeiro.
5. Faça a atualização de forma idempotente.
