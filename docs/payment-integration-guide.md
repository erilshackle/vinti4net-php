# Integração de pagamentos com Vinti4Net v3

Este guia responde ao fluxo completo: instanciar, configurar, iniciar uma compra, definir a URL de retorno, obter referências e validar o callback.

## Instalação e configuração

```bash
composer require erilshk/vinti4net
```

```php
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
    endpoint: null,
);
```

`posId` e `authCode` são fornecidos pela SISP. `endpoint` é opcional e deve ser alterado somente quando a SISP fornecer um endereço diferente, por exemplo para homologação.

## Criar uma compra 3DS

```php
use Eril\Sisp\Billing;

$merchantReference = 'PEDIDO-12345';

$payment = $vinti4->purchase(
    amount: 1500,
    reference: $merchantReference,
    billing: Billing::make()
        ->email('cliente@exemplo.cv')
        ->country('132')
        ->city('Praia')
        ->address('Achada Santo António')
        ->postalCode('7600'),
    currency: 'CVE',
    session: session_id() ?: null,
);
```

Guarde antes do redirecionamento a referência, o valor, a moeda e o estado pendente. O valor deve ser um inteiro positivo em CVE, sem casas decimais.

## Definir o retorno e abrir o gateway

`form()` retorna um documento HTML com formulário auto-submit:

```php
echo $payment->form(
    returnUrl: 'https://exemplo.cv/pagamento/callback',
    lang: 'pt',
);
```

`send()` imprime o mesmo HTML e encerra a execução:

```php
$payment->send('https://exemplo.cv/pagamento/callback', 'pt');
```

A URL deve ser pública e, em produção, usar HTTPS. O idioma padrão é `pt`.

## Dados da operação

| Dado | Origem | Momento de armazenamento |
| --- | --- | --- |
| `merchantReference` | Sua aplicação | Antes de abrir o gateway |
| `merchantSession` | Sua aplicação ou SDK | Antes de abrir o gateway |
| `transactionId` | SISP | Após callback válido e bem-sucedido |
| `clearingPeriod` | SISP | Após callback válido e bem-sucedido |

## Processar e validar o callback

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use Eril\Sisp\Exception\InvalidResponseException;
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);

try {
    $response = $vinti4->processResponse($_POST);
} catch (InvalidResponseException $exception) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

$merchantReference = $response->merchantReference();

// $payment = findPaymentByReference($merchantReference);

if ($response->isSuccess()) {
    $transactionId = $response->transactionId();
    $clearingPeriod = $response->clearingPeriod();
    $amount = $response->amount();
    $currency = $response->currency();

    // Compare referência, valor e moeda e confirme de forma idempotente.
    // saveSispIdentifiers($transactionId, $clearingPeriod);

    echo $response->renderDefaultReceipt('Minha Empresa');
    exit;
}

if ($response->isCancelled()) {
    // Marque como cancelado sem liberar produto ou serviço.
    exit('Pagamento cancelado.');
}

// Marque como falhado sem liberar produto ou serviço.
exit($response->message());
```

`processResponse()` valida o payload e o fingerprint. Ainda assim, a aplicação deve localizar a operação pela referência, comparar os valores esperados e impedir que callbacks repetidos processem o pedido mais de uma vez.

## Recibo customizado

```php
echo $response->renderReceipt(
    template: __DIR__ . '/receipt.php',
    data: ['supportEmail' => 'suporte@exemplo.cv'],
);
```

O template pode ser PHP, HTML ou HTM. No template PHP ficam disponíveis `$receipt` e `$data`; em HTML, use placeholders escapados como `{{ merchantReference }}`.
