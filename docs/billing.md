# Billing 3DS

`Billing` normaliza os dados usados na compra 3DS.

## Builder fluente

```php
use Eril\Sisp\Billing;

$billing = Billing::make()
    ->email('cliente@exemplo.cv')
    ->country('132')
    ->city('Praia')
    ->address('Rua Principal')
    ->postalCode('7600')
    ->mobilePhone('238', '9911234');
```

## Criar a partir de array

```php
$billing = Billing::from([
    'email' => 'cliente@exemplo.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Rua Principal',
    'postalCode' => '7600',
    'mobilePhone' => [
        'cc' => '238',
        'subscriber' => '9911234',
    ],
]);
```

Também são aceitos os nomes equivalentes do gateway, como `billAddrCountry`, `billAddrCity`, `billAddrLine1` e `billAddrPostCode`.

## Normalização

```php
$fields = $billing->toArray();
```

Campos desconhecidos e valores inválidos geram `InvalidRequestException`; eles não são ignorados silenciosamente.
