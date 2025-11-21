<?php

namespace Erilshk\Sisp;

/**
 * Represents billing information to be sent to the Vinti4/SISP gateway.
 *
 * This class provides a fluent builder for billing data, including
 * address, contact information, account details, and fraud-related flags.
 * 
 * It can be instantiated using `make()` or used statically via
 * `create()` to quickly generate structured arrays.
 *
 * @package Erilshk\Vinti4Net
 */
final class Billing
{
    private array $data = [
        'email'            => '',
        'billAddrCountry'  => '132',
        'billAddrCity'     => '',
        'billAddrLine1'    => '',
        'billAddrLine2'    => '',
        'billAddrLine3'    => '',
        'billAddrPostCode' => '',
        'billAddrState'    => '',
        'shipAddrCountry'  => '',
        'shipAddrCity'     => '',
        'shipAddrLine1'    => '',
        'shipAddrPostCode' => '',
        'shipAddrState'    => '',
        'mobilePhone'      => null,
        'workPhone'        => null,
        'acctID'           => '',
        'acctInfo'         => [],
        'suspicious'       => false,
        'addrMatch'        => null,
    ];

    private function __construct() {}

    public static function make(): self
    {
        return new self();
    }

    public static function create(array $data): array
    {
        return self::make()
            ->fill($data)
            ->toArray();
    }

    public function fill(array $data): self
    {
        $map = [
            'email' => 'email',
            'country' => 'billAddrCountry',
            'billAddrCountry' => 'billAddrCountry',
            'city' => 'billAddrCity',
            'billAddrCity' => 'billAddrCity',
            'address' => 'billAddrLine1',
            'billAddrLine1' => 'billAddrLine1',
            'address2' => 'billAddrLine2',
            'billAddrLine2' => 'billAddrLine2',
            'address3' => 'billAddrLine3',
            'billAddrLine3' => 'billAddrLine3',
            'postalCode' => 'billAddrPostCode',
            'billAddrPostCode' => 'billAddrPostCode',
            'state' => 'billAddrState',
            'billAddrState' => 'billAddrState',
            'shipCountry' => 'shipAddrCountry',
            'shipAddrCountry' => 'shipAddrCountry',
            'shipCity' => 'shipAddrCity',
            'shipAddrCity' => 'shipAddrCity',
            'shipAddress' => 'shipAddrLine1',
            'shipAddrLine1' => 'shipAddrLine1',
            'shipPostalCode' => 'shipAddrPostCode',
            'shipAddrPostCode' => 'shipAddrPostCode',
            'shipState' => 'shipAddrState',
            'shipAddrState' => 'shipAddrState',
            'addrMatch' => 'addrMatch',
            'mobilePhone' => 'mobilePhone',
            'phone' => 'mobilePhone',
            'workPhone' => 'workPhone',
            'acctID' => 'acctID',
            'acctInfo' => 'acctInfo',
            'suspicious' => 'suspicious',
        ];

        foreach ($data as $k => $v) {
            if (!isset($map[$k])) {
                continue; // ignora campos desconhecidos
            }

            $field = $map[$k];

            if (in_array($field, ['mobilePhone', 'workPhone']) && is_string($v)) {
                // Suporta string simples para telefone (apenas subscriber)
                $this->data[$field] = ['cc' => '238', 'subscriber' => preg_replace('/\D+/', '', $v)];
            } elseif ($field === 'acctInfo' && is_array($v)) {
                $this->acctInfo($v); // garante defaults
            } elseif ($field === 'addrMatch' && is_bool($v)) {
                $this->addrMatch($v);
            } else {
                $this->data[$field] = $v;
            }
        }

        return $this;
    }


    /* ------------------ Fluent Setters ------------------ */

    public function email(string $v): self { $this->data['email'] = $v; return $this; }
    public function country(string $v): self { $this->data['billAddrCountry'] = $v; return $this; }
    public function city(string $v): self { $this->data['billAddrCity'] = $v; return $this; }
    public function address(string $v): self { $this->data['billAddrLine1'] = $v; return $this; }
    public function address2(string $v): self { $this->data['billAddrLine2'] = $v; return $this; }
    public function address3(string $v): self { $this->data['billAddrLine3'] = $v; return $this; }
    public function postalCode(string $v): self { $this->data['billAddrPostCode'] = $v; return $this; }
    public function state(string $v): self { $this->data['billAddrState'] = $v; return $this; }
    public function shipCountry(string $v): self { $this->data['shipAddrCountry'] = $v; return $this; }
    public function shipCity(string $v): self { $this->data['shipAddrCity'] = $v; return $this; }
    public function shipAddress(string $v): self { $this->data['shipAddrLine1'] = $v; return $this; }
    public function shipPostalCode(string $v): self { $this->data['shipAddrPostCode'] = $v; return $this; }
    public function shipState(string $v): self { $this->data['shipAddrState'] = $v; return $this; }
    public function addrMatch(bool $v): self { $this->data['addrMatch'] = $v ? 'Y' : 'N'; return $this; }

    public function mobilePhone(string $cc, string $subscriber): self
    {
        $this->data['mobilePhone'] = [
            'cc' => $cc,
            'subscriber' => preg_replace('/\D+/', '', $subscriber)
        ];
        return $this;
    }

    public function workPhone(string $cc, string $subscriber): self
    {
        $this->data['workPhone'] = [
            'cc' => $cc,
            'subscriber' => preg_replace('/\D+/', '', $subscriber)
        ];
        return $this;
    }

    public function acctID(string $v): self { $this->data['acctID'] = $v; return $this; }

    public function acctInfo(array $info): self
    {
        $defaults = [
            'chAccAgeInd'           => '01',
            'chAccChange'           => '',
            'chAccDate'             => '',
            'chAccPwChange'         => '',
            'chAccPwChangeInd'      => '01',
            'suspiciousAccActivity' => '01',
        ];
        $this->data['acctInfo'] = array_merge($defaults, $info);
        return $this;
    }

    public function suspicious(bool $v = true): self { $this->data['suspicious'] = $v; return $this; }

    /* ------------------ Final Output ------------------ */
    public function toArray(): array
    {
        return array_filter($this->data, fn($v) => $v !== null && $v !== '');
    }

    /* ------------------ Helpers ------------------ */
    public static function fromUser(array $user): self
    {
        return self::make()
            ->email($user['email'] ?? '')
            ->country($user['country'] ?? '132')
            ->city($user['city'] ?? '')
            ->address($user['address'] ?? '')
            ->address2($user['address2'] ?? '')
            ->address3($user['address3'] ?? '')
            ->postalCode($user['postCode'] ?? '')
            ->state($user['state'] ?? '')
            ->mobilePhone($user['mobilePhoneCC'] ?? '238', $user['mobilePhone'] ?? '')
            ->workPhone($user['workPhoneCC'] ?? '238', $user['workPhone'] ?? '')
            ->acctID($user['id'] ?? '')
            ->acctInfo([
                'chAccAgeInd' => $user['chAccAgeInd'] ?? '05',
                'chAccChange' => isset($user['updated_at']) ? date('Ymd', strtotime($user['updated_at'])) : '',
                'chAccDate' => isset($user['created_at']) ? date('Ymd', strtotime($user['created_at'])) : '',
                'chAccPwChange' => isset($user['updated_at']) ? date('Ymd', strtotime($user['updated_at'])) : '',
                'chAccPwChangeInd' => $user['chAccPwInd'] ?? '05',
                'suspiciousAccActivity' => isset($user['suspicious']) ? ($user['suspicious'] ? '02' : '01') : '01',
            ])
            ->suspicious($user['suspicious'] ?? false);
    }
}
