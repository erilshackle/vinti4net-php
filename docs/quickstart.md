# Quick Start

## 1. Criar instância

```php
use Erilshk\Vinti4Net\Vinti4Net;

$sdk = new Vinti4Net(
    posID: "1234",
    posAuthCode: "SECRETO",
);
```

---

## 2. Preparar pagamento

```php
$billing = [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
];

$sdk->preparePurchasePayment(1000, $billing);
```

---

## 3. Gerar formulário auto-submit

```php
echo $sdk->createPaymentForm("https://meusite.com/callback");
```

---

## 4. Processar callback

```php
$response = $sdk->processResponse($_POST);

if ($response->isSuccess()) {
    // OK
}
```