# Atualização para Vinti4Net 3.0

A versão 3 reorganiza a API pública, elimina o estado mutável da facade, normaliza valores e Billing e introduz respostas, recibos e exceções dedicadas.

Como existem alterações incompatíveis, faça a atualização primeiro num ambiente de homologação.

## Requisitos

- PHP 8.1 ou superior;
- pacote Composer `erilshk/vinti4net`;
- namespace principal `Eril\Sisp`.

```bash
composer require erilshk/vinti4net:^3.0@beta
```

Quando a versão estável estiver disponível:

```bash
composer require erilshk/vinti4net:^3.0
```

## Alterações principais

| v2 | v3 |
| --- | --- |
| `preparePurchase()` | `purchase()` |
| `prepareServicePayment()` | `servicePayment()` |
| `prepareRecharge()` | `recharge()` |
| `prepareRefund()` | `refund()` |
| `setMerchant()` | `reference` e `session` na criação da operação |
| `setRequestParams()` | Parâmetros explícitos no método da operação |
| `createPaymentForm()` | `TransactionRequest::form()` |
| Não havia equivalente direto | `TransactionRequest::send()` |
| `getTransactionId()` | `transactionId()` |
| `getClearingPeriod()` | `clearingPeriod()` |
| `getMerchantRef()` | `merchantReference()` |
| `getAmount()` | `amount()` |
| `getCurrency()` | `currency()` |
| Propriedade `message` | `message()` |
| Propriedade `detail` | `detail()` |
| `generateReceiptHtml()` ou `generateReceipt()` | `renderReceipt()` |
| Recibo customizado antigo | `renderReceipt()` |
| `InvalidArgumentException` | Exceções próprias da biblioteca |

## Configuração

### v2

```php
$vinti4 = new Vinti4Net(
    posID: $_ENV['SISP_POS_ID'],
    posAuthCode: $_ENV['SISP_AUTH_CODE'],
);
```

### v3

```php
$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);
```

Argumentos nomeados precisam ser atualizados para `posId` e `authCode`.

## Compra

### v2

```php
$vinti4->preparePurchase(1500, $billing);
$vinti4->setMerchant('PEDIDO-12345', 'S' . date('YmdHis'));

echo $vinti4->createPaymentForm(
    'https://exemplo.cv/pagamento/callback',
    'pt',
);
```

### v3

```php
$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: $billing,
    currency: 'CVE',
    session: 'S' . date('YmdHis') ?: null,
);

echo $payment->form(
    returnUrl: 'https://exemplo.cv/pagamento/callback',
    lang: 'pt',
);
```

Cada chamada devolve um `TransactionRequest` independente. Assim, várias operações podem ser criadas pela mesma instância sem sobrescrever estado anterior.

Para imprimir o formulário e encerrar a execução:

```php
$payment->send($returnUrl, 'pt');
```

## Billing

```php
use Eril\Sisp\Billing;

$billing = Billing::make()
    ->email('cliente@exemplo.cv')
    ->country('132')
    ->city('Praia')
    ->address('Rua Principal')
    ->postalCode('7600');
```

Ou:

```php
$billing = Billing::from([
    'email' => 'cliente@exemplo.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Rua Principal',
    'postalCode' => '7600',
]);
```

A v3 rejeita campos desconhecidos em vez de ignorá-los silenciosamente.

## Valores

A v3 recebe valores como inteiros positivos em CVE:

```php
amount: 1500
```

Não utilize floats, casas decimais ou separadores. Isso evita arredondamento implícito durante a criação do fingerprint.

## Callback

O ponto de entrada permanece `processResponse()`:

```php
$response = $vinti4->processResponse($_POST);
```

Atualize os getters e valide primeiro o fingerprint:

```php
if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isSuccess()) {
    $reference = $response->merchantReference();
    $transactionId = $response->transactionId();
    $clearingPeriod = $response->clearingPeriod();
    $amount = $response->amount();
    $currency = $response->currency();
}
```

Não registre `raw()` em produção. `toArray()` e `toJson()` fornecem uma representação segura sem PAN bruto nem payload completo.

## Recibos

```php
echo $response->renderReceipt(
    data: [
        'companyName' => 'Minha Empresa',
        'logo' => '/assets/logo.svg',
    ],
);
```

Template próprio:

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/receipt.php',
    data: ['supportEmail' => 'suporte@empresa.cv'],
);
```

## Exceções

Todas as exceções descendem de `Vinti4Exception`:

```php
use Eril\Sisp\Exception\Vinti4Exception;

try {
    echo $payment->form($returnUrl);
} catch (Vinti4Exception $exception) {
    // Trate sem expor credenciais ou payloads sensíveis.
}
```

Exceções específicas:

- `InvalidConfigurationException`;
- `InvalidRequestException`;
- `InvalidResponseException`;
- `ReceiptException`.

## Checklist de migração

- Atualizar os argumentos nomeados do construtor.
- Substituir os quatro métodos `prepare*()`.
- Remover `setMerchant()` e `setRequestParams()`.
- Trocar `createPaymentForm()` por `form()` ou `send()`.
- Atualizar os getters de `Vinti4Response`.
- Atualizar a geração de recibos.
- Capturar `Vinti4Exception` ou uma exceção específica.
- Remover valores monetários em float.
- Testar sucesso, cancelamento, recusa e fingerprint inválido.
- Testar serviço, recarga e reembolso quando habilitados.
- Executar a homologação SISP antes de publicar em produção.
