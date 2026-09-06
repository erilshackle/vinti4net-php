# Campos enviados

A biblioteca monta o pedido, gera o fingerprint e cria o formulário. Normalmente você não precisa montar estes campos manualmente.

## Campos comuns

| Campo | Origem | Regra |
| --- | --- | --- |
| `posID` | Construtor | Fornecido pela SISP |
| `merchantRef` | `setMerchant()` | Obrigatório, máximo 15 caracteres |
| `merchantSession` | `setMerchant()` | Obrigatório, máximo 15 caracteres |
| `amount` | Método `prepare...()` | Inteiro positivo, máximo 13 dígitos |
| `currency` | Compra/configuração | Código ISO numérico com 3 dígitos |
| `transactionCode` | Método usado | `1`, `2`, `3` ou `4` |
| `languageMessages` | `createPaymentForm()` | `pt`, `en` ou `fr` |
| `timeStamp` | Biblioteca | `Y-m-d H:i:s` |
| `fingerprintversion` | Biblioteca | `1` |
| `is3DSec` | Biblioteca | `1` |
| `urlMerchantResponse` | `createPaymentForm()` | URL válida e pública do callback |
| `fingerprint` | Biblioteca | SHA-512/Base64 conforme a SISP |

## Código da transação

| Operação | Código |
| --- | --- |
| Compra 3DS | `1` |
| Pagamento de serviço | `2` |
| Recarga | `3` |
| Reembolso | `4` |

## Moedas reconhecidas

| Moeda | Código |
| --- | --- |
| CVE | `132` |
| USD | `840` |
| EUR | `978` |
| BRL | `986` |
| GBP | `826` |
| JPY | `392` |

Também é aceito um código numérico ISO 4217 com três dígitos. Reembolsos usam apenas `132` (CVE).

## Campos de serviço e recarga

| Campo | Regra |
| --- | --- |
| `entityCode` | Obrigatório e numérico |
| `referenceNumber` | Obrigatório, somente números, até 9 dígitos |

## Campos de reembolso

| Campo | Regra |
| --- | --- |
| `transactionID` | Até 8 caracteres: letras, números ou `_` |
| `clearingPeriod` | Numérico, até 4 dígitos |
| `reversal` | Enviado como `R` pela biblioteca |

## Ver o pedido preparado

```php
$request = $vinti4->getRequest();
```

Use isso apenas para diagnóstico antes da geração do formulário. Não registre segredos nem dados pessoais sem necessidade.

