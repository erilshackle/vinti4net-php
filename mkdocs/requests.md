# Requisições de pagamento

Este documento descreve os parâmetros utilizados para preparar e enviar operações pelo Vinti4Net PHP SDK.

As operações passam por estes métodos:

- `preparePurchase()`;
- `prepareServicePayment()`;
- `prepareRecharge()`;
- `prepareRefund()`;
- `setMerchant()`;
- `setRequestParams()`;
- `createPaymentForm()`.

Os dados de faturação podem ser enviados como array ou objeto `Billing`.

---

## Parâmetros gerais

`setRequestParams()` aceita somente as chaves abaixo:

| Parâmetro | Tipo | Obrigatoriedade | Descrição |
| --- | --- | --- | --- |
| `merchantRef` | `string` | Obrigatório no envio | Referência do comerciante, até 15 caracteres |
| `merchantSession` | `string` | Obrigatório no envio | Sessão do comerciante, até 15 caracteres |
| `languageMessages` | `string` | Não | Idioma da página SISP: `pt`, `en` ou `fr` |
| `entityCode` | `int|string` | Serviço/recarga | Código numérico da entidade |
| `referenceNumber` | `string` | Serviço/recarga | Referência numérica, até 9 dígitos |
| `timeStamp` | `string` | Não | Timestamp da requisição |
| `billing` | `array` | Compra | Dados de faturação e 3DS |
| `currency` | `string|int` | Não | Moeda ISO ou código numérico, como `CVE` ou `132` |
| `acctID` | `string` | Não | ID da conta do titular, até 64 caracteres |
| `acctInfo` | `array` | Não | Informações da conta para 3DS2 |
| `addrMatch` | `string` | Não | `Y` ou `N`, indica se cobrança e entrega coincidem |
| `billAddrCountry` | `string` | Compra | País de faturação |
| `billAddrCity` | `string` | Compra | Cidade de faturação |
| `billAddrLine1` | `string` | Compra | Endereço principal |
| `billAddrPostCode` | `string` | Compra | Código postal |
| `email` | `string` | Compra | E-mail do cliente |
| `clearingPeriod` | `string` | Reembolso | Período contabilístico retornado pela SISP |

Alguns campos dependem do tipo de operação. A biblioteca valida o conjunto final quando `createPaymentForm()` é chamado.

### Exemplo

```php
$sdk->setRequestParams([
    'merchantRef' => 'PEDIDO00000001',
    'merchantSession' => 'S20260906133152',
    'languageMessages' => 'pt',
]);
```

Para referência e sessão, o uso mais simples é:

```php
$sdk->setMerchant('PEDIDO00000001');
```

Uma chave não permitida lança `Vinti4Exception`.

---

## Regras do montante

O montante precisa ser:

- inteiro;
- positivo;
- sem ponto ou vírgula;
- com até 13 dígitos.

```php
$sdk->preparePurchase(1500, $billing); // correto
```

```php
$sdk->preparePurchase(13.51, $billing);   // inválido
$sdk->preparePurchase('13,51', $billing); // inválido
```

As casas decimais não são consideradas para Escudo Cabo-Verdiano no contrato usado pelo SDK.

---

## Exemplo de compra

```php
use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Vinti4Net;

$payment = new Vinti4Net('POS_ID', 'AUTH_CODE');

$billing = Billing::make()
    ->email('cliente@example.cv')
    ->country('132')
    ->city('Praia')
    ->address('Avenida Cidade da Praia, 45')
    ->postalCode('7600');

echo $payment
    ->setMerchant('REF123')
    ->preparePurchase(1500, $billing)
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de serviço

```php
echo $payment
    ->setMerchant('REF456')
    ->prepareServicePayment(2000, 10001, '123456')
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de recarga

```php
echo $payment
    ->setMerchant('REF789')
    ->prepareRecharge(500, 10021, '99123456')
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de reembolso

```php
echo $payment
    ->setMerchant('REFUND001')
    ->prepareRefund(1000, 'TX119922', '1125')
    ->createPaymentForm('https://minha-loja.cv/refund/callback');
```

---

## Inspecionar o pedido

```php
$request = $payment->getRequest();
```

Esse método ajuda a verificar os dados preparados antes do formulário. O fingerprint e os campos finais são criados apenas em `createPaymentForm()`.

## Boas práticas

- Defina referência e sessão com `setMerchant()`.
- Crie a referência no servidor e guarde-a antes do pagamento.
- Use `Billing` para normalizar os dados 3DS.
- Não aceite o montante diretamente do navegador.
- Use uma URL HTTPS pública no callback.
- Não altere os campos do formulário gerado.
- Trate `Vinti4Exception` e registre apenas dados seguros.

Veja também [Pagamentos](payments.md), [Billing](billing.md) e [Respostas](responses.md).

