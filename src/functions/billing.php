<?php

namespace Erilshk\Vinti4Net\functions;

function billing(string $email, string $country, string $city, string $address, string $postCode, $add = []){
    return array_merge($add, [
        'email' => $email,
        'billAddrCountry' => $country,
        'billAddrCity' => $city,
        'billAddrLine1' => $address,
        'billAddrPostCode' => $postCode,
    ]);
}