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

`setRequestParams()` aceita **somente** as quatro chaves abaixo. Os demais dados são argumentos dos métodos `prepare...()` ou são campos internos do `Billing`:

| Parâmetro | Tipo | Obrigatoriedade | Descrição |
| --- | --- | --- | --- |
| `merchantRef` | `string` | Obrigatório no envio | Referência do comerciante; o validador aceita até 15 caracteres, mas use 15 |
| `merchantSession` | `string` | Obrigatório no envio | Sessão do comerciante com exatamente 15 caracteres |
| `languageMessages` | `string` | Não | Idioma da página SISP: `pt`, `en` ou `fr` |
| `timeStamp` | `string` | Não | Timestamp da requisição |

Alguns campos dependem do tipo de operação. A biblioteca valida o conjunto final quando `createPaymentForm()` é chamado.

### Exemplo

```php
$sdk->setRequestParams(['merchantRef' => 'PEDIDO000000001']);
```

Para referência e sessão, o uso mais simples é:

```php
$sdk->setMerchant('PEDIDO000000001'); // gera uma sessão de 15 caracteres
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
    ->setMerchant('PEDIDO000000001')
    ->preparePurchase(1500, $billing)
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de serviço

```php
echo $payment
    ->setMerchant('SERVICO00000001')
    ->prepareServicePayment(2000, $serviceEntityCode, '123456')
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de recarga

```php
echo $payment
    ->setMerchant('RECARGA00000001')
    ->prepareRecharge(500, $rechargeEntityCode, '99123456')
    ->createPaymentForm('https://minha-loja.cv/response');
```

## Exemplo de reembolso

```php
echo $payment
    ->setMerchant('ESTORNO00000001')
    ->prepareRefund(1000, 'TX119922', '1125')
    ->createPaymentForm('https://minha-loja.cv/refund/callback');
```

Os códigos de entidade devem vir da SISP ou da entidade prestadora; `$serviceEntityCode` e `$rechargeEntityCode` são valores da sua aplicação, não constantes fornecidas pelo SDK. Para compra sem dados de billing, chame `preparePurchase(1500, [])`. Com billing, os dados 3DS são enviados apenas dentro de `purchaseRequest` (JSON em Base64), não como inputs separados.

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

