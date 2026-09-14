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
| `endpoint` | Não | URL completa alternativa do gateway; se fornecida, é usada sem anexar `/CardPayment` |

O endpoint usado por padrão é:

```text
https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment
```

Use `endpoint` somente com uma URL **completa** para a operação pretendida (por exemplo, o middleware 3DS de testes, se aplicável). O SDK não acrescenta `/CardPayment` a um endpoint personalizado:

```php
$vinti4 = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
    endpoint: $_ENV['VINTI4_ENDPOINT'], // URL completa, não apenas a URL base
);
```

!!! danger "Proteja o código de autenticação"
    Não coloque `posAuthCode` no HTML, JavaScript, URL, log ou repositório Git. A criação do pagamento e o callback devem ser processados no servidor.

## Opções da próxima transação

`setRequestParams()` guarda opções usadas na próxima transação:

```php
$vinti4->setRequestParams([
    'merchantRef' => 'PEDIDO000000001',
    'merchantSession' => 'S20260906133152',
    'languageMessages' => 'pt',
]);
```

Na maioria dos casos, prefira `setMerchant()` para referência e sessão e passe o idioma em `createPaymentForm()`.

## Idiomas

O formulário aceita:

- `pt`: português;
- `en`: inglês;
- `fr`: francês.

```php
echo $vinti4->createPaymentForm($callbackUrl, 'pt');
```

## Exceções

Todas as falhas da biblioteca usam a mesma exceção na v2.2:

```php
use Erilshk\Sisp\Exceptions\Vinti4Exception;

try {
    $vinti4 = new Vinti4Net($posID, $authCode);
} catch (Vinti4Exception $exception) {
    error_log($exception->getMessage());
}
```

