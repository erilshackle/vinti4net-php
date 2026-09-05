# Instalação

## Requisitos

- PHP 8.1 ou superior;
- Composer 2;
- credenciais POS fornecidas pela SISP;
- URL HTTPS pública para receber o callback.

## Composer

```bash
composer require erilshk/vinti4net
```

Carregue o autoloader:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

## Credenciais

Configure as credenciais por variáveis de ambiente:

```dotenv
SISP_POS_ID=seu-pos-id
SISP_AUTH_CODE=seu-auth-code
```

```php
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);
```

Não publique credenciais no repositório e não as grave em logs.

## Endpoint alternativo

Use `endpoint` somente quando a SISP fornecer um endereço diferente:

```php
$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
    endpoint: $_ENV['SISP_ENDPOINT'],
);
```
