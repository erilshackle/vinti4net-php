<?php

use Erilshk\Vinti4Net\Vinti4Net;

$vinti4 = new Vinti4Net('1234', 'SECRET1234');


$vinti4->preparePurchasePayment(100, [

]);

$vinti4->setRequestParams([
    'languageMessage' => 'pt'
]);


$form = $vinti4->createPaymentForm('http://localhost:8000/callback.php');

echo $form;