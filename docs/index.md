# Vinti4Net PHP SDK

SDK PHP comunitário para integração com o gateway de pagamentos **Rede Vinti4/SISP** de Cabo Verde, Serviço MOP021.

O Vinti4Net v3 suporta:

- compras com autenticação 3D Secure;
- pagamentos de serviços;
- recargas;
- reembolsos;
- validação segura de callbacks;
- recibos padrão e customizados.

!!! warning "Projeto comunitário"
    Este não é um SDK oficial da SISP. O contrato, as credenciais e a documentação fornecidos pela SISP são a autoridade para uso em produção.

## Instalação

```bash
composer require erilshk/vinti4net
```

## Exemplo mínimo

```php
use Eril\Sisp\Billing;
use Eril\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net(
    posId: $_ENV['SISP_POS_ID'],
    authCode: $_ENV['SISP_AUTH_CODE'],
);

$payment = $vinti4->purchase(
    amount: 1500,
    reference: 'PEDIDO-12345',
    billing: Billing::from([
        'email' => 'cliente@exemplo.cv',
        'country' => '132',
        'city' => 'Praia',
        'address' => 'Rua Principal',
        'postalCode' => '7600',
    ]),
);

echo $payment->form('https://exemplo.cv/pagamento/callback');
```

[Começar a integração](quickstart.md){ .md-button .md-button--primary }
[Consultar a API](api.md){ .md-button }
