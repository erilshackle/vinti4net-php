<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Eril\Sisp\Billing;
use Eril\Sisp\Exception\Vinti4Exception;
use Eril\Sisp\Vinti4Net;

try {
    $vinti4 = new Vinti4Net(
        posId: $_ENV['SISP_POS_ID'],
        authCode: $_ENV['SISP_AUTH_CODE'],
    );

    $reference = 'PEDIDO-' . date('YmdHis');

    // Guarde a referência, o valor e a moeda como uma operação pendente.
    $payment = $vinti4->purchase(
        amount: 2500,
        reference: $reference,
        billing: Billing::make()
            ->email('cliente@exemplo.cv')
            ->country('132')
            ->city('Praia')
            ->address('Avenida Cidade de Lisboa')
            ->postalCode('7600'),
        currency: 'CVE',
        session: session_id() ?: null,
    );

    echo $payment->form(
        returnUrl: 'https://exemplo.cv/pagamento/callback.php',
        lang: 'pt',
    );
} catch (Vinti4Exception $exception) {
    http_response_code(422);
    echo 'Não foi possível iniciar o pagamento.';
}
