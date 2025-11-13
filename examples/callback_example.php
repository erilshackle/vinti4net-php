<?php

/**
 * Exemplo de endpoint de callback (postback)
 * 
 * Este arquivo deve ser acessível via URL pública para receber
 * as respostas do Vinti4Net após o pagamento.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Vinti4Net\Vinti4Net;
use Erilshk\Vinti4Net\Vinti4Response;

// =============================================================================
// 1. CONFIGURAÇÃO (mesma do pagamento)
// =============================================================================

$vinti4 = new Vinti4Net(
    posID: 'SEU_POS_ID_AQUI',
    posAuthCode: 'SEU_AUTH_CODE_AQUI'
);

// =============================================================================
// 2. LOG DE DEPURAÇÃO (útil para desenvolvimento)
// =============================================================================

$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'post_data' => $_POST,
    'server_data' => [
        'REMOTE_ADDR' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'HTTP_USER_AGENT' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]
];

file_put_contents('callback_log.json', json_encode($logData, JSON_PRETTY_PRINT), FILE_APPEND);

// =============================================================================
// 3. PROCESSAR RESPOSTA DO SISP
// =============================================================================

try {
    $response = $vinti4->processResponse($_POST);
    
    // =========================================================================
    // 4. TRATAR RESULTADO
    // =========================================================================
    
    if ($response->isSuccess()) {
        // ✅ PAGAMENTO APROVADO
        handleSuccessfulPayment($response);
        
    } elseif ($response->isCancelled()) {
        // ⏹️ PAGAMENTO CANCELADO
        handleCancelledPayment($response);
        
    } elseif ($response->hasInvalidFingerprint()) {
        // ⚠️ ERRO DE SEGURANÇA
        handleSecurityError($response);
        
    } else {
        // ❌ ERRO NO PAGAMENTO
        handleFailedPayment($response);
    }

} catch (Exception $e) {
    // 🚨 ERRO NO PROCESSAMENTO
    handleProcessingError($e);
}

// =============================================================================
// 5. FUNÇÕES DE TRATAMENTO
// =============================================================================

function handleSuccessfulPayment(Vinti4Response $response) {
    // Dados da transação
    $transactionId = $response->getTransactionId();
    $merchantRef = $response->getMerchantRef();
    $amount = $response->getAmount();
    $currency = $response->getCurrency();
    
    // 📝 EXEMPLO: Atualizar banco de dados
    // updateOrderStatus($merchantRef, 'paid', $transactionId);
    
    // 💰 EXEMPLO: Processar DCC (Dynamic Currency Conversion)
    if ($response->dcc['enabled'] ?? false) {
        processDcc($response->dcc);
    }
    
    // 📧 EXEMPLO: Enviar email de confirmação
    // sendConfirmationEmail($merchantRef, $amount, $currency);
    
    // 🧾 Gerar e exibir recibo
    displayReceipt($response, 'Pagamento Aprovado');
}

function handleCancelledPayment(Vinti4Response $response) {
    $merchantRef = $response->getMerchantRef();
    
    // 📝 EXEMPLO: Atualizar status no banco
    // updateOrderStatus($merchantRef, 'cancelled');
    
    displayReceipt($response, 'Pagamento Cancelado');
}

function handleFailedPayment(Vinti4Response $response) {
    $merchantRef = $response->getMerchantRef();
    $errorMessage = $response->message;
    $errorDetail = $response->detail;
    
    // 📝 EXEMPLO: Registrar erro no banco
    // logPaymentError($merchantRef, $errorMessage, $errorDetail);
    
    displayReceipt($response, 'Pagamento Recusado');
}

function handleSecurityError(Vinti4Response $response) {
    // 🚨 ERRO CRÍTICO: Fingerprint inválido
    error_log("ERRO DE SEGURANÇA: " . json_encode([
        'debug' => $response->debug,
        'data' => $response->data
    ]));
    
    displayReceipt($response, 'Erro de Segurança');
}

function handleProcessingError(Exception $e) {
    // 🚨 ERRO NO PROCESSAMENTO
    error_log("ERRO NO CALLBACK: " . $e->getMessage());
    
    http_response_code(500);
    echo "<h2>Erro no Processamento</h2>";
    echo "<p>Ocorreu um erro ao processar a resposta do pagamento.</p>";
    echo "<pre>{$e->getMessage()}</pre>";
}

function processDcc(array $dcc) {
    // Processar informações de conversão de moeda
    error_log("DCC Info: " . json_encode($dcc));
}

function displayReceipt(Vinti4Response $response, string $title) {
    // Gerar recibo HTML
    $receiptHtml = $response->generateReceiptHtml(
        companyName: 'Minha Loja Lda',
        // companyContact: 'suporte@minhaloja.cv | +238 262 0000'
    );
    
    // Exibir página com resultado
    echo "
    <!DOCTYPE html>
    <html lang='pt'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>{$title}</title>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px; 
                background: #f5f5f5; 
            }
            .container { 
                max-width: 600px; 
                margin: 0 auto; 
                background: white; 
                padding: 20px; 
                border-radius: 8px; 
                box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            }
            .title { 
                text-align: center; 
                color: #333; 
                margin-bottom: 30px; 
            }
            .actions { 
                text-align: center; 
                margin-top: 30px; 
                padding-top: 20px; 
                border-top: 1px solid #eee; 
            }
            .btn { 
                display: inline-block; 
                padding: 10px 20px; 
                margin: 0 10px; 
                background: #007bff; 
                color: white; 
                text-decoration: none; 
                border-radius: 4px; 
            }
            .btn-secondary { 
                background: #6c757d; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <h1 class='title'>{$title}</h1>
            {$receiptHtml}
            <div class='actions'>
                <a href='/' class='btn'>Voltar à Loja</a>
                <a href='#' onclick='window.print()' class='btn btn-secondary'>Imprimir Recibo</a>
            </div>
        </div>
    </body>
    </html>
    ";
}

// =============================================================================
// 6. RESPONDER AO SISP (importante!)
// =============================================================================

// O SISP espera uma resposta HTTP 200
http_response_code(200);