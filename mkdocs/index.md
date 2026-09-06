# Vinti4Net PHP SDK 2.2

SDK PHP para integrar pagamentos da Rede Vinti4/SISP em aplicações de Cabo Verde.

```bash
composer require erilshk/vinti4net:^2.2
```

## O que a biblioteca faz

- compra com 3D Secure;
- pagamento de serviço;
- recarga;
- reembolso;
- criação do formulário enviado à Vinti4;
- processamento e validação do callback;
- mensagens claras para erro e cancelamento;
- recibo padrão, personalizado e DCC.

## Fluxo básico

```php
use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net($posID, $authCode);

$billing = Billing::make()
    ->email('cliente@example.cv')
    ->country('132')
    ->city('Praia')
    ->address('Avenida Cidade de Lisboa')
    ->postalCode('7600');

$vinti4
    ->setMerchant('PEDIDO00000001')
    ->preparePurchase(1500, $billing);

echo $vinti4->createPaymentForm(
    'https://loja.example.cv/pagamentos/callback',
    'pt',
);
```

No callback:

```php
$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isSuccess()) {
    // Confira referência, sessão e valor antes de confirmar o pedido.
}
```

[Ver o guia completo](payment-integration-guide.md){ .md-button .md-button--primary }

!!! warning
    Este é um SDK comunitário. O contrato e as credenciais fornecidos pela SISP continuam sendo a fonte oficial para o seu estabelecimento.

