# API Reference – Vinti4Net PHP SDK

Esta página descreve a API pública da versão 2.2 do SDK para integração com Vinti4/SISP.

---

## Classe `Vinti4Net`

É a fachada principal do SDK. Ela prepara pagamentos e reembolsos, gera o formulário e processa o callback.

```php
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net($posID, $posAuthCode, $endpoint);
```

### Construtor

```php
new Vinti4Net(
    string $posID,
    string $posAuthCode,
    ?string $endpoint = null,
)
```

| Parâmetro | Descrição |
| --- | --- |
| `posID` | Identificador POS fornecido pela SISP |
| `posAuthCode` | Código secreto de autenticação |
| `endpoint` | Endpoint alternativo; opcional |

### Métodos principais

| Método | Retorno | Descrição |
| --- | --- | --- |
| `setRequestParams(array $params)` | `self` | Configura parâmetros permitidos |
| `setMerchant(string $reference, ?string $session = null)` | `self` | Define referência e sessão |
| `preparePurchase(float|string $amount, array|Billing $billing, string $currency = 'CVE')` | `static` | Prepara compra 3DS |
| `prepareServicePayment(float|string $amount, int $entity, string $number)` | `static` | Prepara pagamento de serviço |
| `prepareRecharge(float|string $amount, int $entity, string $number)` | `static` | Prepara recarga |
| `prepareRefund(float|string $amount, string $transactionID, string $clearingPeriod)` | `static` | Prepara reembolso |
| `createPaymentForm(string $responseUrl, string $lang = 'pt')` | `string` | Gera o formulário auto-submit |
| `processResponse(array $postData)` | `Vinti4Response` | Processa o retorno da SISP |
| `getRequest()` | `array` | Retorna a requisição preparada |

### Exemplo de compra

```php
$billing = \Erilshk\Sisp\Billing::from([
    'email' => 'cliente@exemplo.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Avenida Cidade da Praia, 45',
    'postalCode' => '7600',
]);

echo $vinti4
    ->setMerchant('REF12345')
    ->preparePurchase(1500, $billing)
    ->createPaymentForm('https://meusite.cv/retorno');
```

---

## Classe `Billing`

Representa e normaliza dados de faturação, endereço, contactos e conta do cliente para compras 3DS.

```php
use Erilshk\Sisp\Billing;

$billing = Billing::make()
    ->email('cliente@exemplo.cv')
    ->country('132')
    ->city('Praia')
    ->address('Avenida Cidade da Praia, 45')
    ->postalCode('7600')
    ->mobilePhone('238', '9912345')
    ->accountId('12345');
```

### Criação e conversão

| Método | Descrição |
| --- | --- |
| `make()` | Cria um builder vazio |
| `from(array $data)` | Cria o Billing a partir de um array |
| `fill(array $data)` | Preenche o objeto existente |
| `toArray()` | Retorna somente os campos preenchidos |

### Dados de faturação

| Método | Campo SISP |
| --- | --- |
| `email()` | `email` |
| `country()` | `billAddrCountry` |
| `city()` | `billAddrCity` |
| `address()` | `billAddrLine1` |
| `address2()` | `billAddrLine2` |
| `address3()` | `billAddrLine3` |
| `postalCode()` | `billAddrPostCode` |
| `state()` | `billAddrState` |

### Entrega, contacto e conta

| Método | Descrição |
| --- | --- |
| `shipCountry()` | País de entrega |
| `shipCity()` | Cidade de entrega |
| `shipAddress()` | Endereço de entrega |
| `shipPostalCode()` | Código postal de entrega |
| `shipState()` | Estado/região de entrega |
| `addressMatchesShipping()` | Define `addrMatch` como `Y` ou `N` |
| `mobilePhone()` | Telefone móvel com país e número |
| `workPhone()` | Telefone de trabalho |
| `accountId()` | ID da conta do cliente |
| `accountInfo()` | Informações 3DS da conta |
| `suspicious()` | Marca ou desmarca atividade suspeita |

---

## Classe `Vinti4Response`

Normaliza o resultado da SISP e expõe estado, mensagem, dados, DCC, debug e detalhe.

```php
$response = $vinti4->processResponse($_POST);

if ($response->isSuccess()) {
    echo 'Aprovada: ' . $response->getAmount();
} elseif ($response->isCancelled()) {
    echo 'Cancelada pelo utilizador.';
} elseif ($response->hasInvalidFingerprint()) {
    echo 'Resposta inválida.';
} else {
    echo $response->message;
}
```

### Propriedades

| Propriedade | Tipo | Descrição |
| --- | --- | --- |
| `status` | `string` | `SUCCESS`, `ERROR`, `CANCELLED` ou `INVALID_FINGERPRINT` |
| `message` | `string` | Mensagem amigável ou erro devolvido |
| `success` | `bool` | Verdadeiro somente em sucesso validado |
| `data` | `array` | Payload original da SISP |
| `dcc` | `array` | Dados DCC normalizados |
| `debug` | `array` | Dados de diagnóstico do fingerprint |
| `detail` | `?string` | Detalhe do erro, quando disponível |

### Métodos de estado

| Método | Descrição |
| --- | --- |
| `isSuccess()` | Confirma sucesso validado |
| `isCancelled()` | Confirma cancelamento |
| `hasInvalidFingerprint()` | Detecta fingerprint inválido |
| `hasFailed()` | Detecta falha que não seja cancelamento |

### Métodos de dados

| Método | Retorno | Campo SISP |
| --- | --- | --- |
| `getTransactionId()` | `?string` | `merchantRespTid` |
| `getClearingPeriod()` | `?string` | `merchantRespCP` |
| `getMerchantRef()` | `?string` | `merchantRespMerchantRef` |
| `getAmount()` | `?float` | `merchantRespPurchaseAmount` |
| `getCurrency()` | `?string` | `merchantRespCurrency` |
| `getAdditionalErrorMessage()` | `string` | `merchantRespAdditionalErrorMessage` |
| `toArray()` | `array` | Resposta normalizada com PAN mascarado |
| `toJson()` | `string` | JSON formatado com PAN mascarado |

### Recibos

| Método | Descrição |
| --- | --- |
| `renderReceipt()` | Recibo padrão ou template personalizado |
| `renderDccReceipt()` | Recibo DCC com dados retornados pela SISP |
| `generateReceiptHtml()` | Compatibilidade da API v2 |
| `generateReceiptText()` | Recibo em texto simples |

### DCC

```php
[
    'enabled' => true,
    'amount' => '10.58',
    'currency' => 'USD',
    'markup' => '0.31',
    'rate' => '92.65882',
]
```

`markup` é um montante, não uma percentagem.

---

## Exceção `Vinti4Exception`

```php
use Erilshk\Sisp\Exceptions\Vinti4Exception;

try {
    echo $vinti4->createPaymentForm($callbackUrl);
} catch (Vinti4Exception $exception) {
    echo $exception->getMessage();
}
```

Na v2.2, as falhas da biblioteca usam essa exceção pública.

