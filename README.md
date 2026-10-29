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

**Sem Composer:** descarregue o ficheiro abaixo e inclua-o com `require_once`:

[Descarregar standalone.php](https://github.com/erilshackle/vinti4net-php/raw/refs/heads/main/dist/standalone.php)

A versão standalone utiliza a classe global `Vinti4Net` e devolve respostas em arrays. Os exemplos deste README utilizam a API Composer.

## Compra com 3D Secure

O exemplo prepara uma compra de **1 500 CVE** com os dados de faturação do cliente e gera o formulário de pagamento da SISP.

Configure as credenciais nas variáveis de ambiente indicadas.

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

O navegador submete automaticamente o formulário à página da Rede Vinti4.

Antes de apresentar o formulário:

- Guarde a referência, a sessão e o montante esperado na sua aplicação. Os dados preparados estão disponíveis em `getRequest()`.
- Preencha `merchantRef` e `merchantSession` com valores não vazios. O SDK não impõe um comprimento fixo.
- Garanta uma referência única por operação na base de dados. `generateMerchantRef()` adiciona aleatoriedade para reduzir colisões.


## Tipos de transação

Os exemplos reutilizam o cliente `$vinti4`. Para cada operação:

1. Gere uma nova `$reference`.
2. Prepare a operação e guarde o pedido.
3. Apresente o formulário de pagamento.

Escolha o método `prepare*()` correspondente ao tipo de pagamento e gere o respetivo formulário com `createPaymentForm()`. Cada pedido representa uma única operação; ao reutilizar o cliente para um novo pedido, o `prepare*()` define os novos dados da operação.

| Operação | Método |
| --- | --- |
| Compra 3DS | `preparePurchase()` |
| Pagamento de serviço | `prepareServicePayment()` |
| Recarga | `prepareRecharge()` |
| Reembolso | `prepareRefund()` |

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

Antes de confirmar o pagamento, verifique:

- A validade do fingerprint.
- A referência e o valor esperados.
- Se a transação já foi processada, para evitar duplicações.

O redirecionamento do navegador, por si só, não confirma o pagamento.

## Tokenização (API Composer, v2.5.0 em desenvolvimento)

> Esta funcionalidade está em desenvolvimento e ainda não está incluída na versão estável indicada na instalação.

A tokenização permite guardar um token associado ao cliente para utilizar em pagamentos futuros. O cliente faz a adesão na página da SISP; a sua aplicação recebe o token no retorno dessa operação.

| Operação | Método | `transactionCode` |
| --- | --- | --- |
| Adesão de token | `prepareTokenRequest()` | `'5'` |
| Compra com token | `prepareTokenPurchase()` | `'6'` |
| Serviço com token | `prepareTokenServicePayment()` | `'2'` |
| Recarga com token | `prepareTokenRecharge()` | `'3'` |
| Cancelamento de token | `prepareTokenCancellation()` | `'7'` |

### Obter o token

Utilize o cliente `$vinti4` configurado anteriormente para preparar a adesão e apresentar o formulário. O montante indicado é o mínimo para pagamentos futuros e não é debitado na adesão.

```php
$vinti4->setMerchant(Vinti4Net::generateMerchantRef());
$vinti4->prepareTokenRequest('100');

echo $vinti4->createPaymentForm('https://example.cv/tokens/resposta');
```

Guarde a referência, a sessão e o tipo de operação na sua aplicação antes de apresentar o formulário.

No endereço de retorno, `$expectedTransactionCode` é o código da operação guardada: na adesão, é a string `'5'`, conforme a tabela acima.

```php
// Código da operação iniciada, recuperado do pedido guardado.
$response = $vinti4->processTokenResponse($_POST, $expectedTransactionCode);

if ($response->isSuccess()) {
    // Confira a referência e a sessão com o pedido guardado antes de persistir.
    $token = $response->getToken();
    $limits = $response->getTokenLimits();

    // Guarde o token, os limites e o consentimento associados ao cliente.
}
```

A variável `$token` vem de `getToken()` após uma adesão bem-sucedida. Nos pagamentos seguintes, recupere esse valor do registo do cliente na sua aplicação. Mantenha-o como string, preservando eventuais zeros iniciais.

O código esperado vem do pedido guardado na sua aplicação. Se for omitido, o SDK valida a assinatura e identifica a categoria da resposta, mas não a compara com a operação iniciada. Consulte os detalhes no [guia de tokenização](docs/tokenization.md).

### Pagar com o token

Com `$token` recuperado do registo do cliente, prepare uma compra:

```php
$vinti4->setMerchant(Vinti4Net::generateMerchantRef());
$vinti4->prepareTokenPurchase($token, '500');

// Guarde o pedido antes de apresentar o formulário.
echo $vinti4->createPaymentForm('https://example.cv/tokens/pagamento-resposta');
```

Para serviço, recarga ou cancelamento, escolha o método correspondente na tabela. Cada pedido utiliza um único `prepare*()`, seguido de `createPaymentForm()`.

Os parâmetros de cada alternativa são:

```php
use Erilshk\Sisp\Entity;

// Pagamento de serviço: token, montante, entidade e referência da fatura.
$vinti4->prepareTokenServicePayment(
    token: $token,
    amount: '2500',
    entity: Entity::SERVICE_AGUAS_SANTIAGO,
    number: '123456789',
);
```

```php
use Erilshk\Sisp\Entity;

// Recarga: token, montante, entidade e número a recarregar.
$vinti4->prepareTokenRecharge(
    token: $token,
    amount: '500',
    entity: Entity::RECHARGE_ALOU,
    number: '9912345',
);
```

```php
// Cancelamento: apenas o token a cancelar.
$vinti4->prepareTokenCancellation(token: $token);
```

As referências e os números acima são ilustrativos. Para cada alternativa, defina uma nova referência com `setMerchant()`, guarde o pedido e apresente o formulário, como no exemplo de compra.


### Mais informações

- [Guia de tokenização](docs/tokenization.md): validação, consentimento, limites e desenvolvimento local.
- [Exemplo interativo](examples/token_example.php).

O SDK prepara formulários e valida respostas. O agendamento de cobranças, as notificações e a reconciliação ficam a cargo da aplicação.

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

São aceites modelos `.php`, `.html` e `.htm`:

- **PHP:** recebem `$receipt` e `$data`.
- **HTML:** utilizam marcadores como `{{ merchantReference }}` e `{{ dcc.amount }}`.

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

Distribuído sob a [licença MIT](LICENSE). Desenvolvido por Erilando TS Carvalho.
