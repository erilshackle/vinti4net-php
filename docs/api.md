# API Reference – Vinti4Net PHP SDK

Este documento descreve a API do SDK PHP para integração com o sistema de pagamentos **Vinti4/SISP (Cabo Verde)**.

---

## 1️⃣ Classe `Vinti4Net`

**Descrição:**
Facade principal do SDK que unifica operações de **pagamentos** e **reembolsos**, simplificando o envio de transações ao SISP.

```php
use Eril\Sisp\Vinti4Net;

$vinti = new Vinti4Net($posID, $posAuthCode, $endpoint);
```

### Métodos principais

| Método                                                        | Parâmetros                                                                                                                                                                                                                                         | Retorno                                      | Descrição                                                |                               |                                  |
| ------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------- | -------------------------------------------------------- | ----------------------------- | -------------------------------- |
| `setRequestParams(array $params)`                             | `merchantRef`, `merchantSession`, `languageMessages`, `entityCode`, `referenceNumber`, `billing`, `currency`, `acctID`, `acctInfo`, `addrMatch`, `billAddrCountry`, `billAddrCity`, `billAddrLine1`, `billAddrPostCode`, `email`, `clearingPeriod` | `self`                                       | Configura parâmetros opcionais para a próxima transação. |                               |                                  |
| `setMerchant(string $reference, ?string $session = null)`     | Referência do merchant e sessão                                                                                                                                                                                                                    | `self`                                       | Define referência e sessão do merchant.                  |                               |                                  |
| `preparePurchase(float                                        | string $amount, array                                                                                                                                                                                                                              | Billing $billing, string $currency = 'CVE')` | Valor, dados de faturação, moeda                         | `self`                        | Prepara pagamento de compra 3DS. |
| `prepareServicePayment(float                                  | string $amount, int $entity, string $number)`                                                                                                                                                                                                      | Valor, entidade, referência                  | `self`                                                   | Prepara pagamento de serviço. |                                  |
| `prepareRecharge(float                                        | string $amount, int $entity, string $number)`                                                                                                                                                                                                      | Valor, entidade, número                      | `self`                                                   | Prepara pagamento de recarga. |                                  |
| `prepareRefund(float                                          | string $amount, string $transactionID, string $clearingPeriod)`                                                                                                                                                                                    | Valor, ID da transação, clearing period      | `self`                                                   | Prepara um reembolso.         |                                  |
| `createPaymentForm(string $responseUrl, string $lang = 'pt')` | URL de retorno, idioma                                                                                                                                                                                                                             | `string`                                     | Gera formulário HTML auto-submissão.                     |                               |                                  |
| `processResponse(array $postData)`                            | Dados recebidos do SISP                                                                                                                                                                                                                            | `Vinti4Response`                             | Processa a resposta do Payment ou Refund.                |                               |                                  |
| `getRequest(): array`                                         | —                                                                                                                                                                                                                                                  | `array`                                      | Retorna os dados preparados da requisição (debug).       |                               |                                  |

---

### Exemplo de uso – Compra 3DS

```php
$billing = \Eril\Sisp\Billing::create([
    'email' => 'cliente@exemplo.com',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Av. Cidade da Praia, 45',
    'postalCode' => '7600'
]);

$vinti->setMerchant('REF12345')
      ->preparePurchase(100.50, $billing)
      ->createPaymentForm('https://meusite.com/retorno');
```

---

## 2️⃣ Classe `Billing`

**Descrição:**
Representa informações de faturação para compras 3DS, incluindo endereço, contatos e informações de conta do cliente.

```php
use Eril\Sisp\Billing;

// Criar billing de forma fluente
$billing = Billing::make()
    ->email('cliente@exemplo.com')
    ->country('132')
    ->city('Praia')
    ->address('Av. Cidade da Praia, 45')
    ->postalCode('7600')
    ->mobilePhone('238', '99123456')
    ->acctID('12345');
```

### Métodos

| Método                | Descrição                                                       |
| --------------------- | --------------------------------------------------------------- |
| `make()`              | Cria instância vazia para fluent setter.                        |
| `create(array $data)` | Cria array pronto para requisição a partir de dados do cliente. |
| `fill(array $data)`   | Preenche dados existentes no objeto Billing.                    |
| `toArray()`           | Retorna array pronto para envio.                                |

---


## 3️⃣ Classe `Vinti4Response`

**Descrição:**
Normaliza a resposta do SISP, expondo status, mensagem amigável, dados, DCC, debug e detalhes adicionais.

```php
use Eril\Sisp\Vinti4Response;

/** Exemplo de processamento */
$response = $vinti->processResponse($_POST);

if ($response->isSuccess()) {
    echo "Transação aprovada: " . $response->getAmount() . " " . $response->getCurrency();
} elseif ($response->isCancelled()) {
    echo "Cancelada pelo usuário.";
} elseif ($response->hasInvalidFingerprint()) {
    echo "Fingerprint inválido!";
} else {
    echo "Erro: " . $response->GetAdditionalErrorMessage();
}
```

### Propriedades

| Propriedade | Tipo    | Descrição                                                               |
| ----------- | ------- | ----------------------------------------------------------------------- |
| `status`    | string  | Status: `SUCCESS`, `ERROR`, `CANCELLED`, `INVALID_FINGERPRINT`.         |
| `message`   | string  | Mensagem amigável do status.                                            |
| `success`   | bool    | `true` se a transação foi bem-sucedida.                                 |
| `data`      | array   | Dados brutos do SISP.                                                   |
| `dcc`       | array   | Informações de DCC (`enabled`, `amount`, `currency`, `markup`, `rate`). |
| `debug`     | array   | Informações de depuração (para fingerprint inválido).                   |
| `detail`    | ?string | Detalhes adicionais de erro.                                            |

---

### DCC (Dynamic Currency Conversion)

* Aplicável somente em **compra 3DS**.
* Estrutura:

```php
[
    'enabled' => true|false,
    'amount' => 100.50,
    'currency' => 'USD',
    'markup' => 5,
    'rate' => 1.2,
    'error' => null
]
```

---


> Para mais exemplos e detalhes de cada parâmetro, consulte [Billing](billing.md) e [respostas](responses.md).