# Recibo padrão e personalizado

Os recibos são gerados a partir de um `Vinti4Response` já processado.

## Recibo padrão

```php
echo $response->renderReceipt(data: [
    'companyName' => 'Minha Empresa, Lda.',
]);
```

O template padrão mostra estado, data, identificação da transação, referência, valor, moeda e cartão mascarado quando existir.

Para estornos aprovados use `renderRefundReceipt()`, não o recibo de pagamento. A SISP pode devolver `merchantRespPurchaseAmount=0`; recupere o valor original no seu banco e forneça-o explicitamente:

```php
echo $response->renderRefundReceipt(
    amount: $originalAmount,
    originalTransactionId: $originalTransactionId,
    data: ['companyName' => 'Minha Empresa, Lda.'],
);
```

Esse método exige uma resposta validada com `messageType=10` e um montante inteiro positivo. Veja [Reembolsos](refunds.md).

## Template PHP

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/recibo.php',
    data: [
        'companyName' => 'Minha Empresa, Lda.',
        'logo' => '/images/logo.png',
    ],
);
```

Dentro do template ficam disponíveis:

- `$receipt`: dados normalizados da transação;
- `$data`: dados personalizados enviados pelo programador.

```php
<h1><?= htmlspecialchars($data['companyName'] ?? 'Recibo') ?></h1>
<p>Referência: <?= htmlspecialchars($receipt['merchantReference']) ?></p>
<p>Total: <?= htmlspecialchars((string) $receipt['amount']) ?> <?= htmlspecialchars($receipt['currency']) ?></p>
```

## Template HTML

```html
<h1>{{ companyName }}</h1>
<p>Transação: {{ transactionId }}</p>
<p>Referência: {{ merchantReference }}</p>
<p>Total: {{ amount }} {{ currency }}</p>
```

```php
echo $response->renderReceipt(
    __DIR__ . '/templates/recibo.html',
    ['companyName' => 'Minha Empresa, Lda.'],
);
```

Os placeholders são escapados para HTML. Caminhos com ponto acessam valores aninhados, como `{{ dcc.amount }}`.

## Dados disponíveis

| Chave | Conteúdo |
| --- | --- |
| `status` | Estado normalizado |
| `success` | Sucesso em booleano |
| `message` / `detail` | Mensagem e detalhe |
| `transactionId` | ID da SISP |
| `merchantReference` | Referência do comerciante |
| `merchantSession` | Sessão do comerciante |
| `amount` / `currency` | Total e moeda |
| `timestamp` | Data/hora da resposta |
| `authorizationCode` | Message ID retornado |
| `entityCode` / `referenceNumber` | Dados de serviço/recarga |
| `reloadCode` | Código de recarga |
| `pan` | Cartão mascarado |
| `dcc` | Dados DCC normalizados |

O cartão é apresentado como `•••• 3456`, nunca com os primeiros dígitos do PAN. Em estornos, `merchantRespPan=0` não representa um cartão e é omitido.

## Texto simples

```php
$text = $response->generateReceiptText('Minha Empresa, Lda.');
```

## Compatibilidade v2

```php
$html = $response->generateReceiptHtml('Minha Empresa, Lda.');
```

Templates precisam existir, ser legíveis e usar `.php`, `.html` ou `.htm`. Caso contrário, a biblioteca lança `Vinti4Exception`.

