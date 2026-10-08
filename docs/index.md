---
layout: home

title: Vinti4Net PHP SDK
titleTemplate: Pagamentos Vinti4/SISP para PHP

hero:
  name: Vinti4Net
  text: Pagamentos Vinti4, simplificados.
  tagline: SDK PHP para integrar pagamentos da Rede Vinti4/SISP em aplicações de Cabo Verde. Da criação da transação à validação da resposta.
  actions:
    - theme: brand
      text: Começar agora
      link: /payment-integration-guide
    - theme: alt
      text: Ver no GitHub
      link: https://github.com/erilshackle/vinti4net

features:
  - icon: 💳
    title: Pagamentos
    details: Compras com 3D Secure, pagamentos de serviços e recargas através da Rede Vinti4.

  - icon: 🔄
    title: Reembolsos
    details: Prepare operações de devolução e gere os respetivos comprovativos.

  - icon: 🔐
    title: Respostas verificadas
    details: Processe callbacks, valide fingerprints e identifique pagamentos aprovados, recusados ou cancelados.

  - icon: 🧾
    title: Recibos
    details: Recibos padrão, personalizados, DCC e de estorno, adaptados às operações suportadas.

  - icon: ⚡
    title: API simples
    details: Prepare transações e gere formulários de pagamento com uma interface PHP intuitiva.

  - icon: 🧩
    title: Independente de frameworks
    details: Integre o SDK na sua aplicação sem impor uma arquitetura ou dependência de framework.
---

## Comece com uma instalação

Disponível através do Composer, o Vinti4Net pode ser adicionado diretamente ao seu projeto PHP.

```bash
composer require erilshk/vinti4net:^2.4.0
```

<small>
  [Packagist](https://packagist.org/packages/erilshk/vinti4net) ·
  [Código-fonte](https://github.com/erilshackle/vinti4net) ·
  [Releases](https://github.com/erilshackle/vinti4net/releases)
</small>

## Do código ao pagamento

Prepare uma compra, configure os dados do cliente e gere o formulário que encaminha a transação para a Rede Vinti4.

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
    ->preparePurchase(1500, $billing);

echo $vinti4->createPaymentForm(
    'https://loja.example.cv/pagamentos/callback',
    'pt'
);
```

::: tip
Os dados de billing são opcionais. Para uma compra sem billing, utilize `preparePurchase(1500, [])`.
:::

## Uma integração, do início ao fim

O Vinti4Net acompanha as principais etapas da comunicação com o gateway, enquanto a sua aplicação mantém o controlo sobre os pedidos e a lógica de negócio.

```mermaid
flowchart LR
    A["Aplicação PHP"] --> B["Vinti4Net"]
    B --> C["Rede Vinti4 / SISP"]
    C --> D["Callback"]
    D --> E["Validação"]
    E --> F["Resultado"]
```

No retorno do pagamento, processe a resposta e verifique a sua autenticidade:

```php
$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isSuccess()) {
    // Validar referência, sessão e valor.
    // Confirmar o pedido na aplicação.
}
```

A confirmação do pagamento deve ocorrer apenas após a validação da resposta e a conferência dos dados da transação com o pedido original.

[Explorar o guia completo de integração →](./payment-integration-guide.md)

## Feito para desenvolvedores PHP

O Vinti4Net abstrai os detalhes da integração com a SISP sem assumir responsabilidades que pertencem à sua aplicação.

Pode utilizá-lo em lojas online, plataformas de reservas, sistemas de cobrança e outros serviços que necessitem de aceitar pagamentos através da Rede Vinti4.

**O SDK prepara e processa transações. A sua aplicação decide o que fazer com os resultados.**

---

## Explore o projeto

- **[Guia de integração](./payment-integration-guide.md)** — implemente o fluxo completo de pagamento.
- **[GitHub](https://github.com/erilshackle/vinti4net)** — consulte o código-fonte e contribua.
- **[Packagist](https://packagist.org/packages/erilshk/vinti4net)** — versões, instalação e dependências.
- **[Issues](https://github.com/erilshackle/vinti4net/issues)** — reporte problemas ou sugira melhorias.

::: info Projeto comunitário
O Vinti4Net é um SDK independente e não oficial da SISP. O contrato, as credenciais e a documentação fornecidos pela SISP continuam a ser a referência oficial para o seu estabelecimento.
:::
