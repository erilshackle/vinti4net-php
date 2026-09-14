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

Ambos são obrigatórios. O validador exige exatamente 15 caracteres para `merchantSession` e aceita até 15 para `merchantRef`; o ambiente SISP pode recusar uma referência com menos de 15. Use referências de 15 caracteres e `setMerchant()` para controlar a sessão.

## URL de callback inválida

Passe uma URL absoluta:

```php
https://loja.example.cv/pagamentos/vinti4/callback
```

Em produção use uma URL HTTPS acessível pelo fluxo configurado pela SISP. Nos testes com middleware/redirecionamento pelo navegador, `localhost` pode funcionar; confirme como o callback é entregue no seu ambiente.

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

