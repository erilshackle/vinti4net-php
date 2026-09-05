# Pagamentos de serviço e recargas

## Pagamento de serviço

```php
$payment = $vinti4->servicePayment(
    amount: 2500,
    entityCode: 10001,
    referenceNumber: '123456789',
    reference: 'SERVICO-12345',
    session: session_id() ?: null,
);

echo $payment->form($returnUrl, 'pt');
```

## Recarga

```php
$payment = $vinti4->recharge(
    amount: 500,
    entityCode: 10021,
    referenceNumber: '9912345',
    reference: 'RECARGA-12345',
    session: session_id() ?: null,
);

echo $payment->form($returnUrl, 'pt');
```

| Campo | Descrição |
| --- | --- |
| `entityCode` | Código da entidade fornecido pela SISP ou prestador |
| `referenceNumber` | Referência de serviço ou recarga |
| `reference` | Identificador único criado pela aplicação |

Não invente códigos de entidade. Use os valores habilitados no contrato do comerciante.
