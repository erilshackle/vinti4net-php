# Erros e diagnóstico

## “Nenhum pagamento preparado”

Você chamou `createPaymentForm()` antes de `preparePurchase()`, `prepareServicePayment()`, `prepareRecharge()` ou `prepareRefund()`.

## “Amount deve ser um inteiro positivo”

Use um valor inteiro entre 1 e 13 dígitos:

```php
$vinti4->preparePurchase(1500, $billing);
```

Não use `1500.00`, `13,51`, zero ou valor negativo.

## Referência ou sessão inválida

Ambos são obrigatórios e aceitam no máximo 15 caracteres. Use `setMerchant()` antes de preparar ou enviar a transação.

## URL de callback inválida

Passe uma URL absoluta:

```php
https://loja.example.cv/pagamentos/vinti4/callback
```

Em testes reais, a SISP precisa conseguir acessar essa URL. `localhost` não é público.

## Código incorreto aparece como fingerprint inválido

Isso indicava uma implementação antiga que tentava validar todo retorno como sucesso. Na v2.2, uma resposta de autenticação recusada deve ficar em `ERROR` e expor a mensagem da SISP.

```php
if ($response->hasFailed()) {
    error_log($response->message);
    error_log($response->detail ?? 'Sem detalhe');
}
```

## Cancelamento

Um payload com `UserCancelled=true` deve produzir `CANCELLED`:

```php
if ($response->isCancelled()) {
    // Mantenha o pedido pendente ou marque a tentativa como cancelada.
}
```

## Billing incompleto

Para compra, confirme estes campos: `email`, `billAddrCountry`, `billAddrCity`, `billAddrLine1` e `billAddrPostCode`.

## DCC incompleto

`renderDccReceipt()` exige que o DCC esteja ativo e que amount, currency, markup e rate tenham sido devolvidos.

## Diagnóstico seguro

```php
error_log($response->toJson());
```

`toJson()` mascara o PAN. Mesmo assim, evite manter logs por mais tempo que o necessário.

