<?php # php -S localhost:8000 -t examples

declare(strict_types=1);

/**
 * Exemplo de criação de pagamento com Vinti4Net.
 *
 * Recebe os dados enviados pelo index.php, prepara a transação
 * e redireciona o cliente para a página de pagamento.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Vinti4Net;

$posId = $_ENV['VINTI4_POS_ID'] ?? '';
$authCode = $_ENV['VINTI4_AUTH_CODE'] ?? '';
$endpoint = $_ENV['VINTI4_ENDPOINT'] ?? null;

$callbackUrl = $_ENV['VINTI4_CALLBACK_URL']
    ?? 'http://localhost:8000/callback_example.php';

$amount = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_INT);
$amount = $amount !== false && $amount !== null ? $amount : 150;

$merchantRef = filter_input(INPUT_POST, 'merchant_ref');
$merchantRef = is_string($merchantRef) && trim($merchantRef) !== ''
    ? trim($merchantRef)
    : 'PEDIDO00000001';

$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$email = is_string($email) ? $email : 'cliente@example.cv';

try {
    $vinti4 = new Vinti4Net(
        posID: $posId,
        posAuthCode: $authCode,
        endpoint: $endpoint
    );

    // Compra 3DS
    $vinti4->preparePurchase(
        amount: $amount,
        billing: Billing::from([
            'email' => $email,
            'country' => '132',
            'city' => 'Praia',
            'address' => 'Avenida Cidade de Lisboa',
            'postalCode' => '7600',
            'mobilePhone' => '9912345',
        ]),
        currency: 'CVE'
    );

    // Pagamento de serviço
    // $vinti4->prepareServicePayment(
    //     amount: $amount,
    //     entity: 10001,
    //     number: '123456789'
    // );

    // Recarga de telemóvel
    // $vinti4->prepareRecharge(
    //     amount: $amount,
    //     entity: 10021,
    //     number: '9912345'
    // );

    // Reembolso
    // $vinti4->prepareRefund(
    //     amount: $amount,
    //     transactionID: '10021',
    //     clearingPeriod: '2511'
    // );

    $vinti4->setMerchant($merchantRef);

    echo $vinti4->createPaymentForm(
        responseUrl: $callbackUrl,
        lang: 'pt'
    );
} catch (InvalidArgumentException $exception) {
    http_response_code(422);

    echo '<h2>Erro de validação</h2>';
    echo '<p>' . htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';

    if (isset($vinti4)) {
        echo '<pre>' . htmlspecialchars(
            print_r($vinti4->getRequest(), true),
            ENT_QUOTES,
            'UTF-8'
        ) . '</pre>';
    }
} catch (Exception $exception) {
    http_response_code(500);

    echo '<h2>Erro ao iniciar o pagamento</h2>';
    echo '<p>' . htmlspecialchars(
        $exception->getMessage(),
        ENT_QUOTES,
        'UTF-8'
    ) . '</p>';
}
echo '<p><a href="/">Voltar</a></p>';
