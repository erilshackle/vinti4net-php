# Reembolsos

O reembolso usa os identificadores devolvidos pela operação original.

```php
$refund = $vinti4->refund(
    amount: 1500,
    transactionId: 'TXN78901',
    clearingPeriod: '2411',
    reference: 'ESTORNO-12345',
    session: session_id() ?: null,
);

echo $refund->form($returnUrl, 'pt');
```

| Parâmetro | Descrição |
| --- | --- |
| `amount` | Valor inteiro positivo a reembolsar |
| `transactionId` | Identificador da transação SISP original |
| `clearingPeriod` | Período de compensação original |
| `reference` | Nova referência própria para o estorno |
| `session` | Sessão opcional do comerciante |

Guarde `transactionId()` e `clearingPeriod()` quando confirmar o pagamento original. Processe o callback do reembolso com o mesmo `processResponse()`.
