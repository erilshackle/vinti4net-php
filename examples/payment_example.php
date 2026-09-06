<?php # php -S localhost:8000 -t examples

/**
 * Exemplo completo de pagamento com Vinti4Net
 * 
 * Este exemplo mostra como criar um pagamento e processar a resposta.
 * 
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Vinti4Net;

// =============================================================================
// 1. CONFIGURAÇÃO INICIAL
// =============================================================================

$posId = $_ENV['VINTI4_POS_ID'] ?? '';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? '';
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;
$callbackUrl = $_ENV['VINTI4_CALLBACK_URL'] ?? '';

try {

    $vinti4 = new Vinti4Net(
        posID: $posId,          // Fornecido pelo SISP
        posAuthCode: $authCode, // Fornecido pelo SISP
        endpoint: $endpoint     // Use null para produção
    );



    // =============================================================================
    // 2. PREPARAR PAGAMENTO (ESCOLHA UM TIPO)
    // =============================================================================



    // PAGAMENTO COM 3DS (COMPRA)
    $vinti4->preparePurchase(
        amount: 150,
        billing: Billing::from([
            'email' => 'cliente@example.cv',
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Avenida Cidade de Lisboa',
            'postalCode' => '7600',
            'mobilePhone' => '9912345',
        ]),
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

    $callbackUrl = 'http://localhost:8000/callback_example.php';

    $vinti4->setMerchant('PEDIDO00000001');

    $paymentForm = $vinti4->createPaymentForm(responseUrl: $callbackUrl, lang: 'pt');

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
