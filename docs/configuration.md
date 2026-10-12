# Configuração

## Criar o cliente

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);
```

| Parâmetro | Obrigatório | Descrição |
| --- | --- | --- |
| `posID` | Sim | Identificador do POS fornecido pela SISP |
| `posAuthCode` | Sim | Código secreto de autenticação fornecido pela SISP |
| `endpoint` | Não | URL alternativa fornecida para a integração |

O endpoint usado por padrão é:

```text
https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment
```

Se a SISP lhe fornecer uma URL alternativa, indique-a em `endpoint`:

```php
$vinti4 = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
    endpoint: $_ENV['VINTI4_ENDPOINT'],
);
```

::: danger Proteja o código de autenticação
Não coloque `posAuthCode` no HTML, JavaScript, URL, log ou repositório Git. A criação do pagamento e o callback devem ser processados no servidor.

:::

## Idiomas

O formulário aceita:

- `pt`: português;
- `en`: inglês;
- `fr`: francês.

```php
echo $vinti4->createPaymentForm($callbackUrl, 'pt');
```

## Exceções

As falhas da biblioteca usam `Vinti4Exception`:

```php
use Erilshk\Sisp\Exceptions\Vinti4Exception;

try {
    $vinti4 = new Vinti4Net($posID, $authCode);
} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
}
```


## Endpoint de tokenização

O terceiro argumento existente do construtor também é usado pelas operações de token:

```php
$vinti4 = new Vinti4Net($posID, $posAuthCode, 'https://gateway.example/TokenPayment');
```

Forneça uma URL completa, não uma base. Sem esse argumento, enrollment, pagamento e cancelamento usam as suas rotas padrão na SISP. Para destinos diferentes, use clientes com os URLs correspondentes. Não há novos argumentos ou setters. Veja [Tokenização](./tokenization).
