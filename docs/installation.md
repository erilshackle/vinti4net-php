# Instalação

## Requisitos

- PHP 8.1 ou superior;
- Composer;
- extensão Hash, normalmente habilitada no PHP;
- credenciais Vinti4Net fornecidas pela SISP;
- uma URL HTTPS acessível no ambiente de produção para receber o callback.

## Composer

```bash
composer require erilshk/vinti4net:^2.4.0
```

O exemplo instala a versão estável 2.4.x. A tokenização descrita neste guia
está planeada para a 2.5.0 e ficará disponível no Composer após a publicação
dessa versão; consulte as [notas de versão](release-notes.md).

Carregue o autoload:

```php
require_once __DIR__ . '/vendor/autoload.php';
```

## Confirmar a instalação

```php
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);
```

Uma credencial vazia ou um endpoint inválido lança `Vinti4Exception`.

## Variáveis de ambiente

```dotenv
VINTI4_POS_ID=seu-pos-id
VINTI4_AUTH_CODE=seu-codigo-secreto
VINTI4_CALLBACK_URL=https://loja.example.cv/pagamentos/vinti4/callback
```

Não envie o `.env` ao Git.

