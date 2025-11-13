<?php

namespace Erilshk\Vinti4Net;

final class Billing
{
    public $email;
    public $billAddrCountry;
    public $billAddrCity;
    public $billAddrLine1;
    public $billAddrLine2;
    public $billAddrLine3;
    public $billAddrPostCode;
    public $mobilePhone;
    public $workPhone;
    public $acctID;
    public $acctInfo;
    public $suspicious;


    /**
     * Gera um array padronizado de billing para pagamentos 3DS.
     *
     * @param array{
     *     email?: string,
     *     country?: string,
     *     city?: string,
     *     address?: string,
     *     address2?: string,
     *     address3?: string,
     *     postalCode?: string,
     *     mobilePhone?: string,
     *     workPhone?: string,
     *     acctID?: string,
     *     acctInfo?: array,
     *     suspicious?: bool
     * } $data Dados do usuário
     *
     * @return array Array pronto para ser passado em preparePurchasePayment
     */
    public static function create(array $data): array
    {
        $get = fn($key, $default = null) => $data[$key] ?? $default;

        return [
            'email'            => $get('email', ''),
            'billAddrCountry'  => $get('billAddrCountry',$get('country', '132')), // CVE por default
            'billAddrCity'     => $get('billAddrCity',$get('city', '')),
            'billAddrLine1'    => $get('billAddrLine1',$get('address', '')),
            'billAddrLine2'    => $get('billAddrLine2',$get('address2', '')),
            'billAddrLine3'    => $get('billAddrLine3',$get('address3', '')),
            'billAddrPostCode' => $get('billAddrPostCode',$get('postalCode', '')),
            'mobilePhone'      => $get('mobilePhone',$get('phone', '')),
            'workPhone'        => $get('workPhone',$get('workPhone', '')),
            'acctID'           => $get('acctID',$get('acctID', '')),
            'acctInfo'         => $get('acctInfo',$get('acctInfo', [])),
            'suspicious'       => $get('suspicious',$get('suspicious', false)),
        ];
    }

    public function toArray() {}
}
