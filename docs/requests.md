
# Requisições de Pagamento

Este documento descreve os parâmetros utilizados para preparar e enviar pagamentos através do **Vinti4Net PHP SDK**.

Todas as operações passam por métodos como:

- `preparePurchase()`
- `prepareServicePayment()`
- `prepareRecharge()`
- `prepareRefund()`
- **`setRequestParams()`**

Os parâmetros podem ser fornecidos diretamente ou via objeto `Billing` para dados de faturação.

---

## Parâmetros gerais de requisição
`setRequestParams()`


| Parâmetro             | Tipo          | Obrigatório | Descrição |
|-----------------------|---------------|------------|-----------|
| `merchantRef`         | string        | Recomendado| Referência do comerciante (até 15 caracteres) |
| `merchantSession`     | string        | Recomendado| Identificador de sessão do comerciante |
| `languageMessages`    | string        | Não        | Código de idioma para mensagens do SISP (`pt`, `en`, `fr`) |
| `entityCode`          | int/string    | Depende    | Código da entidade para pagamentos de serviço ou recarga |
| `referenceNumber`     | string        | Depende    | Número de referência do **serviço** ou **recarga** |
| `timeStamp`           | string        | Não        | Override de timestamp para a requisição |
| `billing`             | array/Billing | Depende    | Dados de faturação do cliente |
| `currency`            | string/int    | Não        | Moeda ISO ou código numérico SISP (ex.: `CVE`, `USD`, `132`) |
| `acctID`              | string        | Não        | ID da conta do portador (3DS2) |
| `acctInfo`            | array         | Não        | Informações adicionais da conta do portador |
| `addrMatch`           | string/YN     | Não        | Indica se o endereço de faturação corresponde ao de envio (`Y`/`N`) |
| `billAddrCountry`     | string        | Depende    | País de faturação (ISO 3166-1 alpha-2) |
| `billAddrCity`        | string        | Depende    | Cidade de faturação |
| `billAddrLine1`       | string        | Depende    | Linha 1 do endereço de faturação |
| `billAddrPostCode`    | string        | Depende    | Código postal de faturação |
| `email`               | string        | Depende    | Email do cliente |
| `clearingPeriod`      | string        | Necessário para reembolso | Período de liquidação exigido pelo SISP |

> Nota: Alguns parâmetros só são necessários dependendo do tipo de transação.

---

## Exemplos de uso

### Pagamento de compra

```php
use Eril\Sisp\Vinti4Net;
use Eril\Sisp\Billing;

$payment = new Vinti4Net('POS_ID', 'AUTH_CODE');

$billing = Billing::make()
    ->email('cliente@example.com')
    ->country('132')
    ->city('Praia')
    ->address('Av. Cidade da Praia, 45')
    ->postalCode('7600');

$payment->preparePurchase(123.45, $billing)
        ->setMerchant('REF123', 'SESSION001')
        ->createPaymentForm('https://minha-loja.com/response');
```

### Pagamento de serviço

```php
$payment->prepareServicePayment(50.00, 10001, '123456')
        ->setMerchant('REF456');
```

### Recarga de telemóvel

```php
$payment->prepareRecharge(10.00, 10021, '99123456')
        ->setMerchant('REF789');
```

### Reembolso

```php
$payment->prepareRefund(50.00, 'TX123456', '2025-11')
        ->createPaymentForm('https://minha-loja.com/response');
```

---

## Boas práticas

* Sempre use `setMerchant()` para definir `merchantRef` e `merchantSession`.
* Use o objeto `Billing` para garantir compatibilidade com 3DS2.
* Nunca inclua parâmetros não listados, caso contrário `InvalidArgumentException` será lançada.
* Para debug, use `$payment->getRequest()` para inspecionar a requisição antes do envio.

---

> Para mais exemplos e detalhes de cada parâmetro, consulte [Billing](billing.md) e [Pagamentos](payments.md).
