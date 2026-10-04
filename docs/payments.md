# Pagamentos

O SDK oferece três tipos de pagamento: compra, pagamento de serviço e recarga. A compra aceita dados adicionais de billing/3DS, mas também pode ser preparada sem eles.

![Exemplo do formulário de pagamento da Vinti4](assets/formulario-pagamento.png)

Antes de preparar qualquer pagamento, crie o cliente e defina uma referência:

```php
use Erilshk\Sisp\Vinti4Net;

$sdk = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);
$reference = Vinti4Net::generateMerchantRef();
$sdk->setMerchant($reference);
```
Use `generateMerchantRef(random: true)` para gerar uma referência totalmente
aleatória, sem incluir a data.

`setMerchant()` também aceita uma sessão personalizada. Se ela não for informada, a biblioteca gera uma sessão no formato `S` + `ymdHis` + `##`.

---

## Purchase Payment (3DS)

Use este método para compras normais com cartão Vinti4:

```php
use Erilshk\Sisp\Billing;

$billing = Billing::from([
    'email' => 'cliente@example.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Avenida Cidade de Lisboa',
    'postalCode' => '7600',
]);

$sdk->preparePurchase(
    amount: '1500',
    billing: $billing,
    currency: 'CVE',
);
```

O Billing pode ser um objeto `Billing` ou um array. Para não enviar dados de billing, passe um array vazio:

```php
$sdk->preparePurchase(1500, []);
```

Se passar dados de billing, informe os campos necessários de faturação. Consulte [Billing 3DS](billing.md).

| Parâmetro | Tipo | Obrigatório | Descrição |
| --- | --- | --- | --- |
| `amount` | `float|string` | Sim | Montante inteiro positivo, até 13 dígitos |
| `billing` | `array|Billing` | Sim, argumento explícito | Dados opcionais de billing; passe `[]` para não os enviar |
| `currency` | `string` | Não | Moeda da operação; o padrão é `Currency::CVE` (`'132'`) |

Embora a assinatura mantenha `float` por compatibilidade da v2, o valor precisa chegar como inteiro. Use `1500`, não `1500.00` nem `13,51`.

---

## Service Payment

Use para pagamentos associados a uma entidade e uma referência de serviço:

```php
$sdk->prepareServicePayment(
    amount: '2000',
    entity: \Erilshk\Sisp\Entity::SERVICE_ALOU_LANDLINE,
    number: '123456789',
);
```

| Parâmetro | Tipo | Descrição |
| --- | --- | --- |
| `amount` | `float|string` | Montante inteiro positivo |
| `entity` | `int` | Código numérico da entidade |
| `number` | `string` | Referência numérica com até 9 dígitos |

O código da entidade deve ser fornecido pela entidade ou pela SISP.

---

## Recharge Payment

Use para recargas associadas a uma entidade e um número de telefone ou conta:

```php
$sdk->prepareRecharge(
    amount: '500',
    entity: \Erilshk\Sisp\Entity::RECHARGE_ALOU,
    number: '9912345',
);
```

Os limites de `entity` e `number` são os mesmos do pagamento de serviço.

---

## Enviar o pagamento

Depois de preparar a operação, gere o formulário que envia o cliente à página Vinti4:

```php
echo $sdk->createPaymentForm(
    responseUrl: 'https://minha-loja.cv/pagamentos/callback',
    lang: 'pt',
);
```

O método retorna uma página HTML com formulário auto-submit. Os idiomas aceitos são `pt`, `en` e `fr`.

```mermaid
sequenceDiagram
    participant Loja
    participant SDK
    participant Vinti4
    participant Callback
    Loja->>SDK: preparePurchase / service / recharge
    Loja->>SDK: createPaymentForm()
    SDK->>Vinti4: Formulário de pagamento
    Vinti4->>Callback: Resultado da operação
    Callback->>SDK: processResponse()
```

O retorno deve ser tratado conforme descrito em [Respostas](responses.md).


## Constantes de moeda

O parâmetro `currency` continua sendo `string`. Use `Currency::CVE` (`132`),
`Currency::USD` (`840`), `Currency::EUR` (`978`), `Currency::BRL` (`986`),
`Currency::GBP` (`826`) ou `Currency::JPY` (`392`).
`Currency::toNumeric('cve')` retorna `'132'`; códigos de três dígitos são
preservados, incluindo zeros à esquerda. Nomes desconhecidos lançam
`Vinti4Exception`.

```php
use Erilshk\Sisp\Currency;

$sdk->preparePurchase('1500', $billing, Currency::CVE);
$code = Currency::toNumeric('EUR'); // '978'
```

Referência: [ISO 4217](https://www.iso.org/iso-4217-currency-codes.html).
As constantes identificam moedas; não garantem suporte do gateway.
O protocolo SISP documentado usa CVE (`132`).


## Constantes de entidade

`Entity` fornece códigos inteiros para `prepareServicePayment()` e
`prepareRecharge()`. Os nomes usam `SERVICE_` para serviços e `RECHARGE_` para
recargas, por exemplo `Entity::SERVICE_ALOU_LANDLINE`,
`Entity::SERVICE_AGUAS_SANTIAGO` e `Entity::RECHARGE_ALOU`.

```php
use Erilshk\Sisp\Entity;

$sdk->prepareRecharge('500', Entity::RECHARGE_ALOU, '9912345');
$entities = Entity::all(); // ['RECHARGE_ALOU' => 2, ...]
$water = Entity::all('water');
$recharges = Entity::all('recharge');
```

Categorias: `recharge`, `electricity`, `water`, `insurance`, `internet`,
`transport` e `telephone`. Uma entidade pode pertencer a mais de uma categoria:
Águas e Energia pertence a água e eletricidade; recargas Electra pertencem a
recarga e eletricidade. O filtro preserva as chaves com os nomes das constantes.
Categorias desconhecidas lançam `Vinti4Exception`.
