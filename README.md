# Vinti4Net PHP SDK

SDK PHP para integração com o sistema de pagamentos **Vinti4Net** ([SISP](https://www.sisp.cv/vinti4.aspx) Cabo Verde, Serviço MOP021).

[![Packagist Version](https://img.shields.io/packagist/v/erilshk/vinti4net)](https://packagist.org/packages/erilshk/vinti4net) [![PHP Version](https://img.shields.io/badge/PHP-8.1%2B-blue.svg)](https://php.net) [![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](LICENSE) [![Build Status](https://img.shields.io/github/actions/workflow/status/erilshackle/vinti4net-php/ci.yml?branch=main&logo=github&label=CI)](https://github.com/erilshackle/vinti4net-php/actions) 


## 📦 Instalação

```bash
composer require erilshk/vinti4net
```

## 🚀 Começo Rápido

### 1. Configuração Básica

```php
<?php

require_once 'vendor/autoload.php';

use Erilshk\Sisp\Vinti4Net;

// Configuração
$vinti4 = new Vinti4Net(
    posID: 'SEU_POS_ID',           // Fornecido pelo SISP
    posAuthCode: 'SEU_AUTH_CODE',  // Fornecido pelo SISP
    endpoint: null                 // Opcional: URL customizada para testes
);
```

### 2. Criar Pagamento

```php
// Pagamento com 3DS (Compra)
$vinti4->preparePurchase(
    amount: 1500.00,
    billing: [
        'email' => 'cliente@email.com',
        'billAddrCountry' => '132', // Código do país (132 = Cabo Verde)
        'billAddrCity' => 'Praia',
        'billAddrLine1' => 'Rua Exemplo, 123',
        'billAddrPostCode' => '7600'
    ],
    currency: 'CVE' // opcional
);

// Ou pagamento de serviço (Água, Luz, etc.)
$vinti4->prepareServicePayment(
    amount: 2500.00,
    entity: 10001,        // Código da entidade (ex: ELECTRA)
    number: '123456789'   // Referência do cliente
);

// Ou recarga de telemóvel
$vinti4->prepareRecharge(
    amount: 500.00,
    entity: 10021,        // Código da operadora (ex: CVMóvel)
    number: '9912345'     // Número de telefone
);
```

### 3. Gerar Formulário de Pagamento

```php
$htmlForm = $vinti4->createPaymentForm(
    responseUrl: 'https://seusite.com/pagamento/callback',
    lang: 'pt' // Opcional: languageMessages
);

echo $htmlForm; // Formulário auto-submissível
```

### 4. Processar Resposta (Callback)

```php
// No seu endpoint de callback (ex: /pagamento/callback)
$response = $vinti4->processResponse($_POST);

if ($response->isSuccess()) {
    // Pagamento aprovado
    $transactionId = $response->getTransactionId();
    $amount = $response->getAmount();
    
    // Atualizar DB
    // Liberar produto/serviço
    
} elseif ($response->isCancelled()) {
    // Usuário cancelou
    echo "Pagamento cancelado pelo usuário";
    
} elseif ($response->hasInvalidFingerprint()) {
    // Erro de segurança
    error_log("Fingerprint inválido: " . json_encode($response->debug));
    
} else {
    // Erro no pagamento
    echo "Erro: " . $response->message;
    echo "Detalhe: " . $response->detail;
}
```

## 📋 Tipos de Transação

| Tipo | Método | Descrição |
|------|--------|-----------|
| 💳 Compra 3DS | `preparePurchase()` | Compras com autenticação 3D Secure |
| 🧾 Serviço | `prepareServicePayment()` | Pagamento de entidades (água, luz, etc.) |
| 📱 Recarga | `prepareRecharge()` | Recarga de telemóvel |
| 💰 Reembolso | `prepareRefund()` | Estorno de transação |

## 🧾 Gerar Recibo

```php
$response = $vinti4->processResponse($_POST);

// Gerar recibo HTML
$receiptHtml = $response->generateReceiptHtml(
    companyName: 'Sua Empresa Lda',
);

echo $receiptHtml;
```

## 🔧 Configuração Avançada

### Parâmetros Customizados

```php
$vinti4->setRequestParams([
    'merchantRef' => 'REF_CUSTOM',
    'merchantSession' => 'SESS_CUSTOM',
    'languageMessages' => 'pt', // ou 'en'
    'timeStamp' => '2024-01-01 12:00:00'
]);
```

### Reembolso

```php
$vinti4->prepareRefund(
    amount: 1500.00,
    merchantRef: 'E_REFERENCE',
    transactionID: 'TXN78901',
    clearingPeriod: '2411'
);
```

## 🛡️ Tratamento de Erros

```php
try {
    $vinti4->preparePurchase(1500, $billing);
    $form = $vinti4->createPaymentForm('https://callback.com');
    echo $form;
    
} catch (InvalidArgumentException $e) {
    echo "Erro de validação: " . $e->getMessage();
    
} catch (Exception $e) {
    echo "Erro geral: " . $e->getMessage();
}
```

## 🧪 Testes

[![Maintenance](https://img.shields.io/maintenance/yes/2025.svg)]() [![Coverage Status](https://img.shields.io/codecov/c/github/erilshackle/vinti4net-php/main?logo=codecov)](https://codecov.io/gh/erilshackle/vinti4net-php)

```bash
# Executar testes
composer test

# Testes com cobertura
composer test-coverage
```

## 📁 Estrutura do Projeto

```
src/
├── Core/
│   ├── Sisp.php          # Classe base abstrata
│   ├── Payment.php       # Operações de pagamento
│   └── Refund.php        # Operações de reembolso
├── Traits/
│   └── ReceiptRenderer.php # Geração de recibos
├── Vinti4Net.php         # Classe principal
├── Billing.php           # Classe para montar Billing
└── Vinti4Response.php    # Resposta processada
```

## 🔗 Links Úteis

- [Documentação]([https://sisp.cv](https://erilshackle.github.io/vinti4net-php/about/)
- [Sisp](https://www.sisp.cv)
- [Vinti4Net](https://vinti4net.cv)
- [Exemplos completos](examples/)

## 📄 Licença

MIT License - veja [LICENSE](LICENSE) para detalhes.

## 🤝 Contribuições

Contribuições são bem-vindas! Por favor, leia [CONTRIBUTING.md](CONTRIBUTING.md) antes de enviar *Pull Requests*.

---

**Desenvolvido com ❤️ para Cabo Verde**

[![GitHub followers](https://img.shields.io/github/followers/erilshackle?label=Follow&style=social)](https://github.com/erilshackle) [![Stars](https://img.shields.io/github/stars/erilshackle/vinti4net-php.svg)](https://github.com/erilshackle/vinti4net-php/stargazers) [![Contributors](https://img.shields.io/github/contributors/erilshackle/vinti4net-php.svg)](https://github.com/erilshackle/vinti4net-php/graphs/contributors)  [![Issues](https://img.shields.io/github/issues/erilshackle/vinti4net-php)](https://github.com/erilshackle/vinti4net-php/issues)




  
