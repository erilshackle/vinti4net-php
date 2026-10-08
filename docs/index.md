---
layout: home
title: Vinti4Net PHP SDK
titleTemplate: Pagamentos Vinti4 para aplicações PHP
description: Integre compras 3D Secure, pagamentos de serviço, recargas e reembolsos da Rede Vinti4/SISP em aplicações PHP.

hero:
  name: Vinti4Net PHP
  text: Pagamentos Vinti4 na sua aplicação.
  tagline: Uma API PHP para preparar pagamentos, validar respostas e emitir recibos. Integração com a Rede Vinti4/SISP em Cabo Verde.
  actions:
    - theme: brand
      text: Começar a integrar
      link: /quickstart
    - theme: alt
      text: Referência da API
      link: /api
    - theme: alt
      text: Novidades da v2.4.0
      link: /release-notes

features:
  - title: Compras com 3D Secure
    details: Prepare a compra e os dados de billing, e encaminhe o cliente para introduzir os dados do cartão na Vinti4.
    link: /billing
    linkText: Configurar billing
  - title: Serviços e recargas
    details: Prepare pagamentos por entidade e referência, ou recargas por telefone ou conta, com constantes de entidade.
    link: /payments
    linkText: Explorar operações
  - title: Respostas verificadas
    details: Valide fingerprints e distinga pagamentos aprovados, erros e cancelamentos no callback.
    link: /responses
    linkText: Processar o retorno
  - title: Reembolsos
    details: Prepare o estorno integral usando os dados da transação original guardados pela sua aplicação.
    link: /refunds
    linkText: Preparar um estorno
  - title: Recibos e DCC
    details: Apresente recibos padrão ou personalizados, recibos de estorno e os valores de conversão DCC devolvidos pela SISP.
    link: /receipt
    linkText: Conhecer os recibos
  - title: API fluente em PHP
    details: Construa billing e dados da conta com métodos explícitos. Use Currency e Entity para identificar moedas e entidades.
    link: /api
    linkText: Consultar a API
---

<div class="vp-doc home-content">

## Comece com Composer

PHP **8.1+** e credenciais POS fornecidas pela SISP.

```bash
composer require erilshk/vinti4net:^2.4
```

## Da sua aplicação à Vinti4

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
    ->setMerchant(Vinti4Net::generateMerchantRef())
    ->preparePurchase('1500', $billing);

echo $vinti4->createPaymentForm(
    'https://loja.example.cv/pagamentos/callback',
    'pt',
);
```

## Receba e valide o resultado

No seu endpoint de callback, use as mesmas credenciais para processar o POST:

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

[Ver o guia completo](payment-integration-guide.md)

::: info Projeto comunitário e independente
Este SDK não é oficial da SISP. A integração segue a especificação técnica disponibilizada pela entidade; o contrato, as credenciais e as orientações da SISP são a referência para o seu estabelecimento. [Conheça o projeto](about.md) e a [Rede Vinti4](https://www.vinti4.cv/).
:::

</div>

<style scoped>
.home-content {
  max-width: 960px;
  margin: 48px auto 0;
  padding: 0 32px 64px;
}
@media (max-width: 639px) {
  .home-content {
    margin-top: 32px;
    padding: 0 24px 40px;
  }
}
</style>
