<?php

namespace Erilshk\Vinti4Net;

final class Billing
{
    private array $data = [
        'email' => '',
        'billAddrCountry' => '132',
        'billAddrCity' => '',
        'billAddrLine1' => '',
        'billAddrLine2' => '',
        'billAddrLine3' => '',
        'billAddrPostCode' => '',
        'mobilePhone' => '',
        'workPhone' => '',
        'acctID' => '',
        'acctInfo' => [],
        'suspicious' => false,
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
            'mobilePhone' => 'mobilePhone',
            'phone' => 'mobilePhone',
            'workPhone' => 'workPhone',
            'acctID' => 'acctID',
            'acctInfo' => 'acctInfo',
            'suspicious' => 'suspicious',
        ];

        foreach ($data as $k => $v) {
            if (!isset($map[$k])) continue;
            $this->data[$map[$k]] = $v;
        }

        return $this;
    }

    public function email(string $v): self { $this->data['email'] = $v; return $this; }
    public function country(string $v): self { $this->data['billAddrCountry'] = $v; return $this; }
    public function city(string $v): self { $this->data['billAddrCity'] = $v; return $this; }
    public function address(string $v): self { $this->data['billAddrLine1'] = $v; return $this; }
    public function address2(string $v): self { $this->data['billAddrLine2'] = $v; return $this; }
    public function postalCode(string $v): self { $this->data['billAddrPostCode'] = $v; return $this; }
    public function mobilePhone(string $v): self { $this->data['mobilePhone'] = $this->cleanPhone($v); return $this; }

    private function cleanPhone(?string $phone): string
    {
        if (!$phone) return '';
        return preg_replace('/\D+/', '', $phone);
    }

    public function toArray(): array
    {
        return $this->data;
    }
}
