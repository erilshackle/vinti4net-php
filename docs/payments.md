# Compra 3DS

Uma compra requer valor, referência própria e dados de Billing.

```php
$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: $billing,
    currency: 'CVE',
    session: 'SESSAO-12345',
);
```

| Parâmetro | Obrigatório | Descrição |
| --- | --- | --- |
| `amount` | Sim | Valor inteiro positivo em CVE |
| `reference` | Sim | Referência única da aplicação |
| `billing` | Sim | Instância de `Billing` ou array |
| `currency` | Não | Moeda, padrão `CVE` |
| `session` | Não | Identificador de sessão do comerciante |

## Renderizar ou enviar

Toda operação devolve um `TransactionRequest`:

```php
$html = $payment->form($returnUrl, 'pt');
```

```php
$payment->send($returnUrl, 'pt');
```

`form()` retorna o HTML. `send()` imprime o HTML e encerra a execução.

## Persistência recomendada

Antes do redirecionamento, guarde:

- referência do comerciante;
- valor e moeda;
- sessão, quando utilizada;
- estado pendente;
- data de criação.
