# Vinti4Net PHP SDK

SDK PHP para integração com o sistema de pagamentos **Vinti4Net** ([SISP](https://www.sisp.cv/vinti4.aspx), Cabo Verde, Serviço MOP021).

[![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4net)](https://packagist.org/packages/erilshk/vinti4net)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE)
[![Build Status](https://img.shields.io/github/actions/workflow/status/erilshackle/vinti4net-php/ci.yml?branch=main&logo=github&label=CI)](https://github.com/erilshackle/vinti4net-php/actions)
[![Coverage](https://codecov.io/gh/erilshackle/vinti4net-php/graph/badge.svg?token=P93P8MGA67)](https://codecov.io/gh/erilshackle/vinti4net-php)

> Este é um SDK comunitário e não oficial da SISP. As especificações, credenciais e orientações fornecidas pela SISP prevalecem sobre esta documentação.

## 📦 [Instalação](https://packagist.org/packages/erilshk/vinti4net)

```bash
composer require erilshk/vinti4net
```

## 🚀 Começo rápido

### 1. Configuração básica

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],       // Fornecido pela SISP
    authCode: $_ENV['SISP_AUTH_CODE'], // Fornecido pela SISP
    endpoint: null,                    // Opcional: endpoint de homologação
);
```

Não coloque as credenciais diretamente no código-fonte. Use variáveis de ambiente ou o sistema de configuração da aplicação.

### 2. Criar pagamento

```php
use Eril\Sisp\Billing;

// Compra com autenticação 3DS
$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: Billing::make()
        ->email('cliente@email.com')
        ->country('132')
        ->city('Praia')
        ->address('Rua Exemplo, 123')
        ->postalCode('7600'),
    currency: 'CVE',
    session: session_id() ?: null,
);
```

Também é possível fornecer o Billing como array:

```php
$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: [
        'email' => 'cliente@email.com',
        'country' => '132',
        'city' => 'Praia',
        'address' => 'Rua Exemplo, 123',
        'postalCode' => '7600',
    ],
);
```

O valor é informado como um inteiro positivo em CVE, por exemplo `1500`.

### 3. Gerar o formulário de pagamento

```php
$htmlForm = $payment->form(
    returnUrl: 'https://seusite.com/pagamento/callback',
    lang: 'pt',
);

echo $htmlForm;
```

O formulário é auto-submissível e encaminha o cliente para a página de pagamento da SISP.

Se preferir imprimir o formulário e terminar imediatamente a execução:

```php
$payment->send(
    returnUrl: 'https://seusite.com/pagamento/callback',
    lang: 'pt',
);
```

### 4. Processar a resposta (callback)

```php
<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Eril\Sisp\Exception\Vinti4Exception;
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);

try {
    $response = $vinti4->processResponse($_POST);
} catch (Vinti4Exception $exception) {
    http_response_code(400);
    exit('Não foi possível processar a resposta.');
}

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta de pagamento inválida.');
}

if ($response->isSuccess()) {
    $merchantReference = $response->merchantReference();
    $transactionId = $response->transactionId();
    $clearingPeriod = $response->clearingPeriod();
    $amount = $response->amount();
    $currency = $response->currency();

    // Localizar o pagamento pela referência.
    // Comparar valor e moeda.
    // Confirmar a operação de forma idempotente.
    // Liberar o produto ou serviço.
} elseif ($response->isCancelled()) {
    echo 'Pagamento cancelado pelo cliente.';
} else {
    echo 'Erro: ' . $response->message();
    echo 'Detalhe: ' . ($response->detail() ?? 'Não informado');
}
```

Nunca confirme um pagamento apenas porque o cliente regressou ao site. Confirme somente após validar o fingerprint e verificar `isSuccess()`.

## 📋 Tipos de transação

| Tipo | Método | Descrição |
| --- | --- | --- |
| 💳 Compra 3DS | `purchase()` | Compra com autenticação 3D Secure |
| 🧾 Serviço | `servicePayment()` | Pagamento de serviço por entidade e referência |
| 📱 Recarga | `recharge()` | Recarga por entidade e número de referência |
| 💰 Reembolso | `refund()` | Estorno de uma transação confirmada |

### Pagamento de serviço

```php
$payment = $vinti4->servicePayment(
    amount: 2500,
    entityCode: 10001,
    referenceNumber: '123456789',
    reference: 'SERVICO-12345',
    session: session_id() ?: null,
);

echo $payment->form('https://seusite.com/pagamento/callback', 'pt');
```

### Recarga

```php
$payment = $vinti4->recharge(
    amount: 500,
    entityCode: 10021,
    referenceNumber: '9912345',
    reference: 'RECARGA-12345',
    session: session_id() ?: null,
);

echo $payment->form('https://seusite.com/pagamento/callback', 'pt');
```

### Reembolso

```php
$refund = $vinti4->refund(
    amount: 1500,
    transactionId: 'TXN78901',
    clearingPeriod: '2411',
    reference: 'ESTORNO-12345',
    session: session_id() ?: null,
);

echo $refund->form('https://seusite.com/pagamento/callback', 'pt');
```

O `transactionId` e o `clearingPeriod` são obtidos na resposta válida da operação original.

## 🧾 Gerar recibo

### Recibo padrão

```php
$response = $vinti4->processResponse($_POST);

echo $response->renderReceipt(
    data: [
        'companyName' => 'Sua Empresa Lda',
        'logo' => '/assets/logo.svg',
    ],
);
```

### Recibo customizado

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/receipt.php',
    data: [
        'supportEmail' => 'suporte@empresa.cv',
    ],
);
```

O template pode ser `.php`, `.html` ou `.htm`. Templates PHP recebem `$receipt` e `$data`; templates HTML podem usar placeholders como `{{ merchantReference }}`.

### Recibo DCC

Quando o cliente escolher pagar noutra moeda através de Dynamic Currency Conversion:

```php
if ($response->dcc()['enabled']) {
    echo $response->renderDccReceipt();
}
```

O recibo DCC segue o modelo bilíngue exigido pela SISP e apresenta o valor original em CVE, a moeda escolhida, a taxa de conversão, o markup e o total convertido. Os valores recebidos da SISP não são recalculados nem arredondados pela biblioteca.

## 🔧 Configuração avançada

### Endpoint customizado

Use um endpoint alternativo apenas quando ele for fornecido pela SISP:

```php
$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
    endpoint: $_ENV['SISP_ENDPOINT'],
);
```

### Referência e sessão próprias

A referência e a sessão são definidas diretamente na criação da operação:

```php
$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: $billing,
    session: 'SESSAO-12345',
);
```

Guarde a referência antes de abrir o gateway. Depois do callback, recupere-a com:

```php
$merchantReference = $response->merchantReference();
```

## 🛡️ Tratamento de erros

```php
use Eril\Sisp\Exception\InvalidConfigurationException;
use Eril\Sisp\Exception\InvalidRequestException;
use Eril\Sisp\Exception\InvalidResponseException;
use Eril\Sisp\Exception\ReceiptException;
use Eril\Sisp\Exception\Vinti4Exception;

try {
    $payment = $vinti4->purchase(
        amount: 1500,
        reference: 'PEDIDO-12345',
        billing: $billing,
    );

    echo $payment->form('https://seusite.com/pagamento/callback');
} catch (InvalidRequestException $exception) {
    echo 'Dados do pagamento inválidos.';
} catch (Vinti4Exception $exception) {
    echo 'Não foi possível iniciar o pagamento.';
}
```

Todas as exceções dedicadas da biblioteca descendem de `Vinti4Exception`.

## 🧪 Testes

[![Maintenance](https://img.shields.io/maintenance/yes/2026.svg)](https://github.com/erilshackle/vinti4net-php)
[![Coverage Status](https://img.shields.io/codecov/c/github/erilshackle/vinti4net-php/main?logo=codecov)](https://app.codecov.io/gh/erilshackle/vinti4net-php/tree/main/src)

```bash
# Instalar dependências
composer install

# Validar o pacote e executar os testes
composer check

# Executar somente os testes
composer test

# Gerar cobertura Clover
composer test-coverage
```

[![Codecov](https://codecov.io/gh/erilshackle/vinti4net-php/graphs/icicle.svg?token=P93P8MGA67)](https://app.codecov.io/gh/erilshackle/vinti4net-php/flags)

## 🔗 Links úteis

- [Documentação](https://erilshackle.github.io/vinti4net-php/)
- [Guia de integração](docs/payment-integration-guide.md)
- [Atualização da v2 para v3](UPGRADE-3.0.md)
- [Changelog](CHANGELOG.md)
- [SISP](https://www.sisp.cv)
- [Vinti4Net](https://vinti4net.cv)
- [Exemplos completos](examples/)

## 📄 Licença

MIT License — veja [LICENSE](LICENSE) para detalhes.

## 🤝 Contribuições

Contribuições são bem-vindas! Leia [CONTRIBUTING](CONTRIBUTING.md) antes de enviar *Pull Requests*.

---

**Desenvolvido com ❤️ para Cabo Verde**

[![GitHub followers](https://img.shields.io/github/followers/erilshackle?label=Follow&style=social)](https://github.com/erilshackle)
[![Stars](https://img.shields.io/github/stars/erilshackle/vinti4net-php.svg)](https://github.com/erilshackle/vinti4net-php/stargazers)
[![Contributors](https://img.shields.io/github/contributors/erilshackle/vinti4net-php.svg)](https://github.com/erilshackle/vinti4net-php/graphs/contributors)
[![Issues](https://img.shields.io/github/issues/erilshackle/vinti4net-php)](https://github.com/erilshackle/vinti4net-php/issues)
