# Billing (3DS Support)

O helper `Billing` normaliza os dados opcionais de faturação para uma compra. Para não enviar billing, passe `[]` explicitamente em `preparePurchase($amount, [])`.

Ele cobre:

- campos obrigatórios de faturação;
- endereço de entrega;
- telefones;
- identificação e histórico da conta;
- indicação de atividade suspeita;
- correspondência entre endereço de faturação e entrega.

---

## Exemplo rápido com `Billing::from()`

Use `from()` quando os dados já estão num array:

```php
use Erilshk\Sisp\Billing;

$billing = Billing::from([
    'email' => 'user@mail.com',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Achada Santo António',
    'postalCode' => '7600',
    'mobilePhone' => '9911122',
]);
```

O objeto pode ser enviado diretamente:

```php
$vinti4->preparePurchase(1500, $billing);
```

Para obter o array normalizado:

```php
$data = $billing->toArray();
```

## Exemplo completo com chaining

```php
$billing = Billing::make()
    ->email('user@mail.com')
    ->country('132')
    ->city('Praia')
    ->address('Achada Santo António')
    ->address2('Bloco B, Apt 10')
    ->address3('Próximo ao mercado')
    ->postalCode('7600')
    ->state('PR')
    ->shipCountry('132')
    ->shipAddress('Rua de Entrega, 45')
    ->shipCity('Praia')
    ->shipPostalCode('7601')
    ->shipState('PR')
    ->mobilePhone('238', '9911122')
    ->workPhone('238', '2612345')
    ->accountId('123456')
    ->accountInfo([
        'chAccAgeInd' => '05',
        'chAccChange' => '20230101',
        'chAccDate' => '20220101',
        'chAccPwChange' => '20230201',
        'chAccPwChangeInd' => '05',
        'suspiciousAccActivity' => '01',
    ])
    ->addressMatchesShipping(false)
    ->suspicious(false);
```

`make()` é indicado quando os dados são adicionados de forma gradual. `toArray()` remove campos vazios.

---

## Campos obrigatórios

| Campo SISP | Nome amigável | Tipo | Descrição |
| --- | --- | --- | --- |
| `email` | `email` | `string` | E-mail do titular |
| `billAddrCountry` | `country` | `string` | País em código numérico, como `132` |
| `billAddrCity` | `city` | `string` | Cidade de faturação |
| `billAddrLine1` | `address` | `string` | Endereço principal |
| `billAddrPostCode` | `postalCode` | `string` | Código postal |

`billAddrCountry` usa `132` por padrão. Se informar billing, forneça email, cidade e morada. Quando o código postal não for informado, o pedido usa `0000`. Para não enviar dados 3DS adicionais, passe `[]` em `preparePurchase()`.

## Campos opcionais de faturação

| Campo SISP | Nome amigável | Método |
| --- | --- | --- |
| `billAddrLine2` | `address2` | `address2()` |
| `billAddrLine3` | `address3` | `address3()` |
| `billAddrState` | `state` | `state()` |

## Endereço de entrega

| Campo SISP | Nome amigável | Método |
| --- | --- | --- |
| `shipAddrCountry` | `shipCountry` | `shipCountry()` |
| `shipAddrCity` | `shipCity` | `shipCity()` |
| `shipAddrLine1` | `shipAddress` | `shipAddress()` |
| `shipAddrPostCode` | `shipPostalCode` | `shipPostalCode()` |
| `shipAddrState` | `shipState` | `shipState()` |

Use `addressMatchesShipping(true)` quando o endereço de entrega corresponde ao endereço de faturação. O método aceita `true`, `false`, `Y` ou `N` (também em minúsculas). `Y` copia país, cidade, morada, código postal e subdivisão de cobrança para entrega; `N` preserva a entrega informada.

---

## Telefones

```php
$billing
    ->mobilePhone('238', '9911122')
    ->workPhone('238', '2612345');
```

A estrutura final é:

```php
[
    'cc' => '238',
    'subscriber' => '9911122',
]
```

Ao usar `Billing::from()`, também pode passar apenas o número. Nesse caso, a biblioteca usa `238` como código padrão.

```php
Billing::from([
    'phone' => '9911122',
    // Ou: 'mobilePhone' => ['cc' => '238', 'subscriber' => '9911122'],
]);
```

Para `+2389911122` ou números de outros países, informe `cc` e `subscriber` separadamente. A classe retira caracteres não numéricos, mas **não** interpreta automaticamente um prefixo internacional; `+2389911122` passado como uma string inteira duplicaria o indicativo.

---

## Dados da conta

### `acctID`

Identifica a conta do cliente no sistema do comerciante. Pode ser o ID do utilizador ou o email usado para entrar no site:

```php
$billing->accountId('CLIENTE-123');
```

O limite de `acctID` é validado dentro do billing: 64 caracteres.

### `acctInfo`

```php
$billing->accountInfo([
    'chAccAgeInd' => '05',
    'chAccDate' => '20220101',
    'chAccChange' => '20230101',
    'chAccPwChange' => '20230201',
    'chAccPwChangeInd' => '05',
]);
```

`chAccAgeInd` e `chAccPwChangeInd` são omitidos quando não informados. `suspiciousAccActivity` usa `01` quando dados da conta são fornecidos. A biblioteca não inventa o histórico do cliente.

As datas de `acctInfo` são enviadas como fornecidas; use o formato `YYYYMMDD`. `created_at` e `updated_at` não são aliases de `Billing::from()` e serão ignorados. Mapeie campos do seu `$user` explicitamente para as chaves SISP. `fromUser()` permanece apenas para compatibilidade: preserva os indicadores informados e usa `password_changed_at` para a senha, nunca `updated_at`.

### Atividade suspeita

```php
$billing->suspicious(true);
```

`true` define `suspiciousAccActivity` como `02`; `false` define como `01`.

---

## Nomes amigáveis e nomes SISP

`Billing::from()` aceita os dois formatos:

```php
Billing::from([
    'country' => '132',
    'billAddrCity' => 'Praia',
    'address' => 'Plateau',
    'billAddrPostCode' => '7600',
    'email' => 'user@mail.com',
]);
```

Campos desconhecidos são ignorados.

Um payload 3DS já no formato SISP também pode ser passado diretamente:

```php
$billing = Billing::from([
    'email' => 'cliente@example.cv',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Rua Principal, 1',
    'billAddrPostCode' => '7600',
    'addrMatch' => 'N',
    'mobilePhone' => ['cc' => '238', 'subscriber' => '9912345'],
    'acctInfo' => ['chAccDate' => '20220328'],
]);
```

Para dados da sua aplicação, faça o mapeamento com nomes explícitos:

```php
$billing = Billing::from([
    'email' => $user['email'],
    'city' => $user['city'],
    'address' => $user['address'],
    'postalCode' => $user['postal_code'],
    'accountId' => (string) $user['id'],
    'mobilePhone' => ['cc' => '238', 'subscriber' => $user['phone']],
]);
```

Nesse exemplo, `$user['phone']` deve conter o número local, sem `+238`. O `Billing` não garante que os valores recebidos correspondem a um telefone, país ou endereço válidos: essa verificação pertence à aplicação.

---



## Conta com parâmetros nomeados

Use o método fluente `account()` para evitar um array específico da aplicação:

```php
$billing->account(
    id: (string) $user['id'],
    createdAt: '20261001',
    changedAt: '20261002',
    passwordChangedAt: '20261003',
    ageIndicator: '03',
    passwordChangeIndicator: '03',
    suspicious: false,
);
```

Datas: `YYYYMMDD`; `changedAt` é a última alteração do perfil, enquanto
`passwordChangedAt` é exclusivamente a última alteração da senha.
`accountInfo()` continua aceitando as chaves SISP para quem usa a especificação.

| Campo de `acctInfo` | Significado |
| --- | --- |
| `chAccAgeInd` | Idade da conta |
| `chAccDate` | Data de criação da conta |
| `chAccChange` | Data da última alteração do perfil |
| `chAccPwChange` | Data da última alteração da senha |
| `chAccPwChangeInd` | Indicador do período da alteração da senha |
| `suspiciousAccActivity` | `01`: não suspeito; `02`: suspeito |

Indicadores de idade: `01` sem conta, `02` durante a transação, `03` menos de
30 dias, `04` entre 30 e 60 dias, `05` mais de 60 dias. O cenário sem conta
(`chAccAgeInd=01`) depende de exceção aprovada pela SISP.

## Normalização e validação

- Uma segunda linha de cobrança vazia recebe a primeira linha.
- Código postal desconhecido recebe `0000` ao gerar o pedido.
- Email e limite de `acctID` (64 caracteres) são validados. Os formatos e limites dos campos opcionais seguem a documentação da SISP, sem validação restritiva na lib.
- País e subdivisão são preservados, sem catálogo ou conversão automática: `132`
  e `CPV` podem ser fornecidos ao helper. A SISP documenta país numérico ISO 3166-1
  (`132`) e subdivisão ISO 3166-2 (`PR` para Praia); preservar `CPV` não garante
  sua aceitação pelo gateway.
- Telefone pessoal pode preencher `workPhone` explicitamente quando necessário;
  a biblioteca não copia contactos automaticamente.
