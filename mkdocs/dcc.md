# Recibo DCC

DCC permite que o titular veja e aceite o pagamento na moeda apresentada pela SISP.

## Detectar DCC

```php
if (($response->dcc['enabled'] ?? false) === true) {
    echo $response->renderDccReceipt();
}
```

## Dados disponíveis

| Chave | Origem | Significado |
| --- | --- | --- |
| `enabled` | `dcc` | DCC foi aplicado |
| `amount` | `dccAmount` | Total na moeda DCC |
| `currency` | `dccCurrency` | Moeda escolhida |
| `markup` | `dccMarkup` | Montante da taxa do serviço DCC |
| `rate` | `dccRate` | Taxa de conversão informada |

Exemplo de dados normalizados:

```php
[
    'enabled' => true,
    'amount' => '10.58',
    'currency' => 'USD',
    'markup' => '0.31',
    'rate' => '92.65882',
]
```

O recibo apresenta:

- taxa de conversão;
- taxa do serviço DCC;
- moeda da transação;
- total em CVE;
- total na moeda DCC;
- declaração de escolha da moeda;
- identificação da rede Vinti4 e do fornecedor da taxa.

!!! warning
    `dccMarkup` é um montante, não uma percentagem. Use os valores devolvidos pela SISP sem recalcular ou arredondar.

Se os dados obrigatórios estiverem ausentes, `renderDccReceipt()` lança `Vinti4Exception`.
