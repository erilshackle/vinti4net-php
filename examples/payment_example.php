<?php

/**
 * Exemplo completo de pagamento com Vinti4Net
 * 
 * Este exemplo mostra como criar um pagamento e processar a resposta.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Vinti4Net;

// =============================================================================
// 1. CONFIGURAÇÃO INICIAL
// =============================================================================

$vinti4 = new Vinti4Net(
    posID: 'SEU_POS_ID_AQUI',          // Fornecido pelo SISP
    posAuthCode: 'SEU_AUTH_CODE_AQUI', // Fornecido pelo SISP
    endpoint: null                     // Use null para produção
);

// =============================================================================
// 2. PREPARAR PAGAMENTO (ESCOLHA UM TIPO)
// =============================================================================

try {
    // PAGAMENTO COM 3DS (COMPRA)
    $vinti4->preparePurchase(
        amount: 2500,
        billing: [
            'email' => 'cliente@exemplo.cv',
            'billAddrCountry' => '132',    // Cabo Verde
            'billAddrCity' => 'Praia',
            'billAddrLine1' => 'Avenida Cidade da Praia, 45',
            'billAddrPostCode' => '7600',
            'user' => [
                'mobilePhone' => '+238 9912345',
                'created_at' => '2023-01-01 00:00:00'
            ]
        ],
        currency: 'CVE'
    );

    // PAGAMENTO DE SERVIÇO
    // $vinti4->prepareServicePayment(
    //     amount: 3500.00,
    //     entity: 10001,           // ELECTRA
    //     number: '123456789'      // Referência do cliente
    // );

    // RECARGA DE TELEMÓVEL
    // $vinti4->prepareRecharge(
    //     amount: 1000.00,
    //     entity: 10021,           // CVMóvel
    //     number: '9912345'        // Número de telefone
    // );

    // ESTORNO
    // $vinti4->prepareRefund(
    //     amount:          2500,
    //     transactionID:  '10021',     
    //     clearingPeriod: '2511'
    // );

    // =========================================================================
    // 3. GERAR FORMULÁRIO DE PAGAMENTO
    // =========================================================================

    $callbackUrl = 'https://seusite.com/pagamento/callback.php';
    $merchantRef = 'R' . date('YmdHis'); // Referência única

    $vinti4->setMerchant(reference: $merchantRef);

    $paymentForm = $vinti4->createPaymentForm(responseUrl: $callbackUrl, lang: 'en');

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