<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Vinti4Net\Vinti4Net;

// =============================================================================
// 1. CONFIGURAÇÃO INICIAL
// =============================================================================

$vinti4 = new Vinti4Net(
    posID: '90000443',          // Fornecido pelo SISP
    posAuthCode: '9G0UpvtnLXo7Mfa9', // Fornecido pelo SISP
    endpoint: "https://3dsteste.vinti4net.cv/3ds_middleware_php/public/3ds_init.php"                     // Use null para produção
);

// =============================================================================
// 2. PREPARAR PAGAMENTO (ESCOLHA UM TIPO)
// =============================================================================

try {
    // PAGAMENTO COM 3DS (COMPRA)
    $vinti4->preparePurchasePayment(
        amount: 2500,
        billing: [
            'email' => 'cliente@exemplo.cv',
            'billAddrCountry' => '132',    // Cabo Verde
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Avenida Cidade da Praia, 45',
            'billAddrPostCode' => '7600',
        ]
    );

    // PAGAMENTO DE SERVIÇO
    // $vinti4->prepareServicePayment(
    //     amount: 3500.00,
    //     entity: 10001,           // ELECTRA
    //     number: '123456789'      // Referência do cliente
    // );

    // RECARGA DE TELEMÓVEL
    // $vinti4->prepareRechargePayment(
    //     amount: 1000.00,
    //     entity: 10021,           // CVMóvel
    //     number: '9912345'        // Número de telefone
    // );

    // =========================================================================
    // 3. GERAR FORMULÁRIO DE PAGAMENTO
    // =========================================================================

    $callbackUrl = 'http://localhost:8000/examples/callback_example.php';
    $merchantRef = 'PEDIDO_' . date('YmdHis'); // Referência única
    
    $paymentForm = $vinti4->createPaymentForm($callbackUrl);


    // =========================================================================
    // 4. EXIBIR FORMULÁRIO (auto-submissão)
    // =========================================================================
    
    echo $paymentForm;

} catch (InvalidArgumentException $e) {
    echo "<h2>Erro de Validação</h2>";
    echo "<p>{$e->getMessage()}</p>";
    echo "<pre>" . print_r($vinti4->getRequest(), true) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2>Erro no Sistema</h2>";
    echo "<p>{$e->getMessage()}</p>";
}