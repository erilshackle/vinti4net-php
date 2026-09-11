# Vinti4Net PHP SDK

SDK PHP comunitário para integração com a **Rede Vinti4 / SISP**, em Cabo Verde (serviço MOP021).

[![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4net)](https://packagist.org/packages/erilshk/vinti4net)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![CI](https://img.shields.io/github/actions/workflow/status/erilshackle/vinti4net-php/ci.yml?branch=main&logo=github&label=CI)](https://github.com/erilshackle/vinti4net-php/actions)
[![Codecov](https://codecov.io/gh/erilshackle/vinti4net-php/graph/badge.svg?token=P93P8MGA67)](https://codecov.io/gh/erilshackle/vinti4net-php)

> Este projeto não é um SDK oficial da SISP. A documentação e o contrato fornecidos pela SISP são a autoridade para credenciais, entidades e requisitos de produção.

## Instalação

```bash
composer require erilshk/vinti4net:^2.2
```

Requer PHP 8.1 ou superior.

## Pagamento mínimo

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);

$billing = Billing::from([
    'email' => 'cliente@example.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Avenida Cidade de Lisboa',
    'postalCode' => '7600',
]);

try {
    $vinti4
        ->setMerchant(reference: 'PEDIDO000000001')
        ->preparePurchase(amount: 1500, billing: $billing);

    echo $vinti4->createPaymentForm(
        responseUrl: 'https://example.cv/pagamentos/callback',
        lang: 'pt',
    );
} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(400);
}
```

O formulário é auto-submetido para a página da Rede Vinti4. `merchantRef` e `merchantSession` aceitam no máximo 15 caracteres.

## Tipos de transação

| Operação | Método |
| --- | --- |
| Compra 3DS | `preparePurchase()` |
| Pagamento de serviço | `prepareServicePayment()` |
| Recarga | `prepareRecharge()` |
| Reembolso | `prepareRefund()` |

### Pagamento de serviço

```php
$vinti4
    ->setMerchant('SERVICO00000001')
    ->prepareServicePayment(
        amount: 2500,
        entity: 10001,
        number: '123456789',
    );
```

### Recarga

```php
$vinti4
    ->setMerchant('RECARGA00000001')
    ->prepareRecharge(
        amount: 500,
        entity: 10021,
        number: '9912345',
    );
```

### Reembolso

```php
$vinti4
    ->setMerchant('REFUND000000001')
    ->prepareRefund(
        amount: 1500,
        transactionID: 'TXN78901',
        clearingPeriod: '2411',
    );

echo $vinti4->createPaymentForm(
    'https://example.cv/pagamentos/refund-callback'
);
```

## Processar o callback

Configure um endpoint HTTPS público e use as mesmas credenciais POS do pedido:

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    $_ENV['VINTI4_POS_ID'],
    $_ENV['VINTI4_AUTH_CODE'],
);

try {
    $response = $vinti4->processResponse($_POST);

    if ($response->hasInvalidFingerprint()) {
        http_response_code(400);
        exit('Resposta inválida.');
    }

    if ($response->isSuccess()) {
        $transactionId = $response->getTransactionId();
        $merchantRef = $response->getMerchantRef();
        $amount = $response->getAmount();

        // Confirme a referência e o valor esperados e torne a atualização idempotente.
    } elseif ($response->isCancelled()) {
        // O cliente cancelou a operação.
    } else {
        // A operação foi recusada ou falhou.
    }

    http_response_code(200);
} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(400);
}
```

Não confirme pagamentos apenas pelo redirecionamento do navegador. Valide sempre o fingerprint, a referência, o valor e se a transação ainda não foi processada.

## Recibos

### Recibo padrão

```php
echo $response->renderReceipt(data: [
    'companyName' => 'Minha Empresa, Lda.',
    'logo' => '/assets/logo.svg',
]);
```

### Template próprio

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/receipt.php',
    data: ['supportEmail' => 'suporte@example.cv'],
);
```

Templates `.php`, `.html` e `.htm` são aceitos. Templates PHP recebem `$receipt` e `$data`; templates HTML utilizam placeholders como `{{ merchantReference }}` e `{{ dcc.amount }}`.

### Recibo DCC

```php
if (($response->dcc['enabled'] ?? false) === true) {
    echo $response->renderDccReceipt();
}
```

O recibo DCC exibe exatamente os valores validados enviados pela SISP. A biblioteca não recalcula `amount`, não arredonda `rate` e não acrescenta `%` a `markup`.

Os métodos antigos continuam disponíveis na v2.2:

```php
$response->generateReceiptHtml('Minha Empresa');
$response->generateReceiptText('Minha Empresa');
```

## Tratamento de erros

Todas as falhas da biblioteca usam uma única exceção:

```php
use Erilshk\Sisp\Exceptions\Vinti4Exception;

try {
    // integração
} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
}
```

## Testes

```bash
composer test
composer test-coverage
```

## Atualização

Consulte [UPGRADE-2.2.md](UPGRADE-2.2.md) antes de atualizar a partir da v2.1.

## Links

- [Documentação](https://erilshackle.github.io/vinti4net-php/)
- [Packagist](https://packagist.org/packages/erilshk/vinti4net)
- [SISP](https://www.sisp.cv)
- [Exemplos](examples/)

## Licença

Distribuído sob a licença MIT. Consulte [LICENSE](LICENSE).
