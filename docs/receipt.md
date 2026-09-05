# Recibos

## Recibo padrão

```php
echo $response->renderReceipt(
    data: [
        'companyName' => 'Minha Empresa',
        'logo' => '/assets/logo.svg',
    ],
);
```

O recibo padrão é minimalista e utiliza os dados normalizados da resposta.

## Template próprio

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/receipt.php',
    data: [
        'supportEmail' => 'suporte@empresa.cv',
    ],
);
```

São suportados templates `.php`, `.html` e `.htm`.

### Template PHP

Templates PHP recebem `$receipt` e `$data`:

```php
<h1><?= htmlspecialchars($data['companyName'] ?? 'Recibo') ?></h1>
<p>Referência: <?= htmlspecialchars($receipt['merchantReference'] ?? '') ?></p>
```

### Template HTML

Use placeholders escapados:

```html
<h1>Recibo</h1>
<p>Referência: {{ merchantReference }}</p>
<p>Transação: {{ transactionId }}</p>
```

Erros de leitura ou renderização geram `ReceiptException`.

## Recibo DCC

Quando o cliente aceitar a conversão de moeda oferecida pela Rede Vinti4, utilize o recibo DCC oficial:

```php
if ($response->dcc()['enabled']) {
    echo $response->renderDccReceipt();
}
```

O recibo apresenta:

- taxa de conversão de uma unidade da moeda DCC para CVE;
- markup como valor na moeda DCC, não como percentagem;
- moeda escolhida pelo cliente;
- total original em CVE;
- total convertido na moeda DCC;
- declarações bilíngues exigidas pela SISP.

Exemplo de dados normalizados:

```php
[
    'enabled' => true,
    'amount' => '10.58',
    'currency' => 'USD',
    'rate' => '92.65882',
    'markup' => '0.31',
]
```

!!! warning "Não altere os valores DCC"
    O recibo apresenta exatamente os valores validados enviados pela SISP. A biblioteca não recalcula o total, não arredonda a taxa e não acrescenta `%` ao markup.

`renderDccReceipt()` lança `ReceiptException` quando a resposta não contém uma operação DCC ou quando os dados necessários estão incompletos.
