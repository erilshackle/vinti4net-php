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
composer require erilshk/vinti4net:^2.4
```

Requer PHP 8.1 ou superior.

## Compra com 3D Secure

O exemplo prepara uma compra de 1 500 CVE com os dados de faturação do cliente e gera o formulário de pagamento da SISP. Configure as credenciais nas variáveis de ambiente indicadas.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Erilshk\Sisp\Vinti4Net;
use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;

// Credenciais POS fornecidas pela SISP.
$vinti4 = new Vinti4Net(
    posID: getenv('VINTI4_POS_ID'),
    posAuthCode: getenv('VINTI4_AUTH_CODE'),
);

// Dados de faturação para a compra 3DS.
$billing = Billing::from([
    'email' => 'cliente@example.cv',
    'country' => '132',
    'city' => 'Praia',
    'address' => 'Avenida Cidade de Lisboa',
    'postalCode' => '7600',
]);

try {
    // Referência única da tentativa de pagamento.
    $reference = Vinti4Net::generateMerchantRef();
    $vinti4->setMerchant(reference: $reference);

    // Montante em CVE, obtido na aplicação.
    $vinti4->preparePurchase(amount: '1500', billing: $billing);

    // Encaminhar o cliente para a página de pagamento da SISP.
    echo $vinti4->createPaymentForm(
        responseUrl: 'https://example.cv/pagamentos/callback',
        lang: 'pt',
    );

} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
    http_response_code(400);
}
```

Antes de apresentar o formulário, guarde a referência, a sessão e o montante esperado na sua aplicação. Pode obter os dados preparados com `getRequest()`. O navegador submete automaticamente o formulário à página da Rede Vinti4. `merchantRef` e `merchantSession` devem ser não vazios, sem comprimento fixo imposto pelo SDK. Guarde uma referência única para cada operação; `generateMerchantRef()` adiciona aleatoriedade para reduzir colisões, mas a aplicação ainda deve garantir a unicidade da referência na base de dados.


## Tipos de transação

Os exemplos reutilizam o cliente `$vinti4`. Gere uma nova `$reference` para cada operação e guarde o pedido antes de apresentar o formulário. Cada chamada `prepare*()` substitui a operação anterior.

| Operação | Método |
| --- | --- |
| Compra 3DS | `preparePurchase()` |
| Pagamento de serviço | `prepareServicePayment()` |
| Recarga | `prepareRecharge()` |
| Reembolso | `prepareRefund()` |
| Adesão de token | `prepareTokenRequest()` |
| Compra com token | `prepareTokenPurchase()` |
| Serviço com token | `prepareTokenServicePayment()` |
| Recarga com token | `prepareTokenRecharge()` |
| Cancelamento de token | `prepareTokenCancellation()` |

### Tokenização (API Composer, v2.5.0 em desenvolvimento)

Esta funcionalidade está em desenvolvimento e ainda não está incluída na versão estável indicada na instalação. O construtor de `Vinti4Net` mantém os três argumentos existentes. O argumento opcional `endpoint` também personaliza o URL de token, sem métodos adicionais de configuração. Por padrão, as operações usam os endpoints `/TokenEnrollment`, `/TokenPayment` e `/TokenCancel` em `https://mc.vinti4net.cv/BizMPIOnUsSisp`. Consulte o [guia de tokenização](docs/tokenization.md) e o [exemplo interativo](examples/token_example.php).

```php
$client = new Vinti4Net(
    posID: $posId,
    posAuthCode: $posAuthCode,
);
$client->prepareTokenRequest('100');
echo $client->createPaymentForm('https://merchant.example/token-response');

// Em pedidos separados, com novas referências persistidas:
$client->prepareTokenPurchase($token, '500');
$client->prepareTokenServicePayment($token, '500', 6, '227045251');
$client->prepareTokenRecharge($token, '500', 2, '9573234');
$client->prepareTokenCancellation($token);

// No callback, o código esperado vem do pedido persistido pela aplicação:
$response = $client->processTokenResponse($_POST, $expectedTransactionCode);
if ($response->isSuccess()) {
    $token = $response->getToken();
    $limits = $response->getTokenLimits();
}
```

Cada preparação substitui a operação anterior. Gere o formulário depois da operação pretendida. Tokens mantêm-se strings; não converta para inteiro. A adesão não debita o montante mínimo. Os dados do cartão e o código de utilização única (OTP) são recolhidos na página da SISP; a aplicação guarda o token e o consentimento do cliente após validar a resposta. A assinatura de versão 1 é independente de `Payment` e os callbacks A/B/C só resultam em sucesso após validação. Correlacione a referência e a sessão com o pedido antes de guardar dados ou atualizar estados. O SDK prepara formulários e valida respostas; o agendamento de cobranças, as notificações e a reconciliação ficam a cargo da aplicação.

Para desenvolvimento, callbacks HTTP em `localhost`, `127.0.0.1` e `::1` são aceites pelo SDK. Para testar o retorno real da SISP, utilize HTTPS local ou um túnel HTTPS público; veja as notas de sessão no guia.

### Pagamento de serviço

Exemplo de pagamento de uma fatura de Águas de Santiago. Use o montante e a referência da fatura; o número abaixo é ilustrativo.

```php
use Erilshk\Sisp\Entity;
$vinti4
    ->setMerchant($reference)
    ->prepareServicePayment(
        amount: 2500,
        entity: Entity::SERVICE_AGUAS_SANTIAGO,
        number: '123456789',
    );
```

### Recarga

Exemplo de recarga Alou de 500 CVE. Substitua o número ilustrativo pelo número indicado pelo cliente.

```php
use Erilshk\Sisp\Entity;
$vinti4
    ->setMerchant($reference)
    ->prepareRecharge(
        amount: 500,
        entity: Entity::RECHARGE_ALOU,
        number: '9912345',
    );
```

### Reembolso

Recupere o identificador da transação e o período de compensação guardados após a compra aprovada. Os valores abaixo são ilustrativos.

```php
$vinti4
    ->setMerchant($reference)
    ->prepareRefund(
        amount: 1500,
        transactionID: '3456',
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

// Use as mesmas credenciais POS do pedido.
$vinti4 = new Vinti4Net(
    getenv('VINTI4_POS_ID'),
    getenv('VINTI4_AUTH_CODE'),
);

try {
    // Validar os dados enviados pela SISP.
    $response = $vinti4->processResponse($_POST);

    // Rejeitar uma resposta com assinatura inválida.
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

### Recibo de reembolso

Após confirmar o reembolso e validar o fingerprint, recupere o valor original na sua aplicação. A SISP pode devolver montante zero na resposta de reembolso.

```php
echo $response->renderRefundReceipt(
    amount: '1500',
    originalTransactionId: '3456',
    data: ['companyName' => 'Minha Empresa, Lda.'],
);
```

### Modelo personalizado

```php
echo $response->renderReceipt(
    template: __DIR__ . '/templates/receipt.php',
    data: ['supportEmail' => 'suporte@example.cv'],
);
```

São aceites modelos `.php`, `.html` e `.htm`. Os modelos PHP recebem `$receipt` e `$data`; os modelos HTML utilizam marcadores como `{{ merchantReference }}` e `{{ dcc.amount }}`.

### Recibo DCC

```php
if ($response->isDccEnabled()) {
    echo $response->renderDccReceipt();
}
```

O recibo DCC exibe exatamente os valores validados enviados pela SISP. A biblioteca não recalcula `amount`, não arredonda `rate` e não acrescenta `%` a `markup`.

Os métodos antigos continuam disponíveis na v2.3:

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
    // Registe o erro e apresente uma mensagem adequada ao cliente.
}
```

## Documentação e recursos

- [Documentação](https://erilshackle.github.io/vinti4net-php/)
- [Packagist](https://packagist.org/packages/erilshk/vinti4net)
- [SISP](https://www.sisp.cv)

## Licença

Distribuído sob a licença MIT. Consulte a licença do repositório.
Desenvolvido por Erilando TS Carvalho.
