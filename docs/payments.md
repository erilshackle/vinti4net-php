# Purchase Payment (3DS)

Use este método para compras normais com cartão Vinti4:

```php
$sdk->preparePurchasePayment(
    amount: 1500,
    billing: Billing::create($dadosDeFaturacao),
    currency: 'CVE'
);
```

---

# Service Payment

```php
$sdk->prepareServicePayment(
    amount: 2000,
    entity: 341,
    number: "123456789"
);
```

---

# Recharge Payment

```php
$sdk->prepareRechargePayment(
    amount: 500,
    entity: 341,
    number: "987654321"
);
```