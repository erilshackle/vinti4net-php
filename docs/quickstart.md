# Quick Start

## 1. Criar instância

```php
use Eril\Sisp\Vinti4Net;

$sdk = new Vinti4Net(
    posID: "1234",
    posAuthCode: "SECRETO",
);
```

---

## 2. Preparar pagamento

```php
// Os dados de Billing são obrigatórios, segundo a documentação da SISP, ela compõe o PurchaseRequest.
$billing = [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Cidade',
    'billAddrLine1' => 'endereço',
    'billAddrPostCode' => '7601',
];

$sdk->preparePurchase(1000, $billing);
```

---

## 3. Gerar formulário auto-submit

```php
echo $sdk->createPaymentForm("https://meusite.com/callback");
```

---

## 4. Processar callback

```php
// na página de resposta de retorno:
$response = $sdk->processResponse($_POST);

if ($response->isSuccess()) {
    // OK
}
```