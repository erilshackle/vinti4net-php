# Recibos

## Recibo padrão

```php
echo $response->renderDefaultReceipt(
    companyName: 'Minha Empresa',
    logo: '/assets/logo.svg',
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
