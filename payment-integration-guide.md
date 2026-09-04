# Integração de pagamentos com Vinti4Net

Este guia mostra como configurar o `erilshk/vinti4net`, iniciar uma compra 3DS, definir a URL de retorno e validar a resposta enviada pela SISP.

## Instalação

```bash
composer require erilshk/vinti4net
```

## 1. Criar e iniciar o pagamento

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Eril\Sisp\Billing;
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posID: $_ENV['SISP_POS_ID'],
    posAuthCode: $_ENV['SISP_POS_AUTH_CODE'],
);

$merchantRef = 'PEDIDO-12345';

$billing = Billing::make()
    ->email('cliente@email.com')
    ->country('132')
    ->city('Praia')
    ->address('Achada Santo António')
    ->postalCode('7600');

try {
    $paymentForm = $vinti4
        ->preparePurchase(
            amount: 1500.00,
            billing: $billing,
            currency: 'CVE',
        )
        ->setMerchant(
            reference: $merchantRef,
            session: session_id(),
        )
        ->createPaymentForm(
            responseUrl: 'https://exemplo.cv/pagamento/callback',
            lang: 'pt',
        );

    echo $paymentForm;
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo 'Dados de pagamento inválidos.';
} catch (Throwable $exception) {
    http_response_code(500);

    echo 'Não foi possível iniciar o pagamento.';
}
```

O HTML retornado por `createPaymentForm()` contém um formulário auto-submit que encaminha o cliente para o ambiente de pagamento da SISP.

Antes de apresentar o formulário, guarde a intenção de pagamento na base de dados:

```php
[
    'merchant_ref' => $merchantRef,
    'amount' => 1500.00,
    'currency' => 'CVE',
    'status' => 'pendente',
]
```

## Configuração necessária

A instância de `Vinti4Net` recebe:

| Parâmetro | Descrição |
| --- | --- |
| `posID` | Identificação POS fornecida pela SISP. |
| `posAuthCode` | Credencial de autenticação fornecida pela SISP. |
| `endpoint` | URL alternativa do gateway, quando necessária. É opcional. |

```php
$vinti4 = new Vinti4Net(
    posID: $_ENV['SISP_POS_ID'],
    posAuthCode: $_ENV['SISP_POS_AUTH_CODE'],
    endpoint: null,
);
```

Não coloque as credenciais diretamente no código-fonte. Carregue-as por variáveis de ambiente ou pelo sistema de configuração da aplicação.

## Dados exigidos para uma compra 3DS

Uma compra é preparada com:

```php
preparePurchase(
    float|string $amount,
    array|Billing $billing,
    string $currency = 'CVE',
)
```

Os dados básicos de faturação documentados são:

```php
$billing = [
    'email' => 'cliente@email.com',
    'billAddrCountry' => '132',
    'billAddrCity' => 'Praia',
    'billAddrLine1' => 'Achada Santo António',
    'billAddrPostCode' => '7600',
];
```

Além do valor e dos dados de faturação, a aplicação deve fornecer:

- uma `merchantRef` própria e única;
- a moeda da transação, como `CVE`;
- uma `responseUrl` HTTPS acessível pela SISP.

## Definir a referência do pagamento

A referência interna é definida com `setMerchant()`:

```php
$vinti4->setMerchant(
    reference: 'PEDIDO-12345',
    session: session_id(),
);
```

A `merchantRef` identifica o pagamento dentro da aplicação. Ela deve ser única e guardada antes de redirecionar o cliente.

## Definir a URL de retorno

A URL de callback é passada para `createPaymentForm()`:

```php
$form = $vinti4->createPaymentForm(
    responseUrl: 'https://exemplo.cv/pagamento/callback',
    lang: 'pt',
);
```

Depois da operação, a SISP envia os dados da resposta por `POST` para essa URL.

## 2. Processar e validar o callback

No endpoint `/pagamento/callback`:

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posID: $_ENV['SISP_POS_ID'],
    posAuthCode: $_ENV['SISP_POS_AUTH_CODE'],
);

try {
    $response = $vinti4->processResponse($_POST);
} catch (Throwable $exception) {
    http_response_code(400);

    echo 'Não foi possível processar a resposta.';
    return;
}

$merchantRef = $response->getMerchantRef();

// Procure o pagamento local pela merchantRef.
// $payment = Payment::findByMerchantRef($merchantRef);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);

    // Não aprove nem libere o produto ou serviço.
    echo 'Resposta de pagamento inválida.';
    return;
}

if ($response->isSuccess()) {
    $transactionId = $response->getTransactionId();
    $clearingPeriod = $response->getClearingPeriod();
    $amount = $response->getAmount();
    $currency = $response->getCurrency();

    /*
     * Confirme o pagamento de forma idempotente e guarde:
     *
     * - merchant_ref;
     * - transaction_id;
     * - clearing_period;
     * - amount;
     * - currency;
     * - status = pago.
     */

    echo $response->generateReceiptHtml('Minha Empresa');
    return;
}

if ($response->isCancelled()) {
    // Atualize como cancelado, sem liberar o produto ou serviço.
    echo 'Pagamento cancelado pelo cliente.';
    return;
}

// Atualize como falhado, sem liberar o produto ou serviço.
echo 'O pagamento não foi concluído.';
```

## Referência própria e transação SISP

Os identificadores têm finalidades diferentes:

| Identificador | Origem | Como obter |
| --- | --- | --- |
| `merchantRef` | Criado pela aplicação | Definido em `setMerchant()` e recuperado com `getMerchantRef()`. |
| `transactionId` | Gerado pela SISP | Recuperado depois do callback com `getTransactionId()`. |
| `clearingPeriod` | Gerado pela SISP | Recuperado com `getClearingPeriod()`. |

O `transactionId` e o `clearingPeriod` devem ser guardados quando a resposta for validada. Eles serão necessários caso a transação precise ser reembolsada.

## Validação do retorno

O retorno deve ser processado exclusivamente por:

```php
$response = $vinti4->processResponse($_POST);
```

Depois, verifique:

```php
$response->isSuccess();
$response->isCancelled();
$response->hasInvalidFingerprint();
```

Nunca confirme o pagamento apenas porque o cliente regressou à aplicação. Confirme somente quando `processResponse()` aceitar o retorno e `isSuccess()` devolver `true`.

Também é recomendado:

- localizar o pagamento pela `merchantRef` guardada previamente;
- impedir que o mesmo callback confirme a operação mais de uma vez;
- comparar valor e moeda retornados com a intenção de pagamento local;
- não guardar credenciais ou dados sensíveis em logs;
- rejeitar respostas com fingerprint inválido;
- usar HTTPS na URL de callback.

## Resumo do fluxo

1. Crie `Vinti4Net` com `posID` e `posAuthCode`.
2. Monte os dados 3DS com `Billing`.
3. Prepare a compra com `preparePurchase()`.
4. Defina a referência com `setMerchant()`.
5. Guarde localmente o pagamento como pendente.
6. Passe o callback para `createPaymentForm()`.
7. Apresente o formulário auto-submit.
8. No callback, execute `processResponse($_POST)`.
9. Rejeite fingerprint inválido.
10. Confirme somente quando `isSuccess()` retornar `true`.
11. Guarde `transactionId` e `clearingPeriod`.

## Referências

- [Repositório Vinti4Net](https://github.com/erilshackle/vinti4net-php)
- [Pacote no Packagist](https://packagist.org/packages/erilshk/vinti4net)
