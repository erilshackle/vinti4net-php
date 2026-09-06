<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

/**
 * Represents billing and 3D Secure customer information.
 */
final class Billing
{
    /** @var array<string, mixed> */
    private array $data = [
        'email' => '',
        'billAddrCountry' => '132',
        'billAddrCity' => '',
        'billAddrLine1' => '',
        'billAddrLine2' => '',
        'billAddrLine3' => '',
        'billAddrPostCode' => '',
        'billAddrState' => '',
        'shipAddrCountry' => '',
        'shipAddrCity' => '',
        'shipAddrLine1' => '',
        'shipAddrPostCode' => '',
        'shipAddrState' => '',
        'mobilePhone' => null,
        'workPhone' => null,
        'acctID' => '',
        'acctInfo' => [],
        'addrMatch' => null,
    ];

    private function __construct()
    {
    }

    /**
     * Create an empty billing builder.
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Create a billing builder from an array.
     *
     * @param array<string, mixed> $data Billing and 3D Secure customer data.
     */
    public static function from(array $data): self
    {
        return self::make()->fill($data);
    }

    /**
     * Create normalized billing data from an array.
     *
     * @param array<string, mixed> $data Billing and 3D Secure customer data.
     *
     * @return array<string, mixed>
     *
     * @deprecated 2.2.0 Use Billing::from($data)->toArray().
     */
    public static function create(array $data): array
    {
        return self::from($data)->toArray();
    }

    /**
     * Fill the billing builder using friendly or SISP field names.
     *
     * Unknown fields are ignored for backward compatibility with v2.
     *
     * @param array<string, mixed> $data Billing and 3D Secure customer data.
     */
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
            'mobilePhone' => 'mobilePhone',
            'phone' => 'mobilePhone',
            'workPhone' => 'workPhone',
            'accountId' => 'acctID',
            'acctID' => 'acctID',
            'accountInfo' => 'acctInfo',
            'acctInfo' => 'acctInfo',
            'addressMatchesShipping' => 'addrMatch',
            'addrMatch' => 'addrMatch',
        ];

        foreach ($data as $key => $value) {
            if ($key === 'suspicious') {
                $this->suspicious((bool) $value);
                continue;
            }

            $field = $map[$key] ?? null;

            if ($field === null) {
                continue;
            }

            if ($field === 'mobilePhone' || $field === 'workPhone') {
                $this->data[$field] = $this->normalizePhone($value);
                continue;
            }

            if ($field === 'acctInfo' && is_array($value)) {
                $this->accountInfo($value);
                continue;
            }

            if ($field === 'addrMatch') {
                $this->data[$field] = is_bool($value)
                    ? ($value ? 'Y' : 'N')
                    : $value;
                continue;
            }

            $this->data[$field] = $value;
        }

        return $this;
    }

    public function email(string $value): self
    {
        $this->data['email'] = trim($value);
        return $this;
    }

    public function country(string $value): self
    {
        $this->data['billAddrCountry'] = trim($value);
        return $this;
    }

    public function city(string $value): self
    {
        $this->data['billAddrCity'] = trim($value);
        return $this;
    }

    public function address(string $value): self
    {
        $this->data['billAddrLine1'] = trim($value);
        return $this;
    }

    public function address2(string $value): self
    {
        $this->data['billAddrLine2'] = trim($value);
        return $this;
    }

    public function address3(string $value): self
    {
        $this->data['billAddrLine3'] = trim($value);
        return $this;
    }

    public function postalCode(string $value): self
    {
        $this->data['billAddrPostCode'] = trim($value);
        return $this;
    }

    public function state(string $value): self
    {
        $this->data['billAddrState'] = trim($value);
        return $this;
    }

    public function shipCountry(string $value): self
    {
        $this->data['shipAddrCountry'] = trim($value);
        return $this;
    }

    public function shipCity(string $value): self
    {
        $this->data['shipAddrCity'] = trim($value);
        return $this;
    }

    public function shipAddress(string $value): self
    {
        $this->data['shipAddrLine1'] = trim($value);
        return $this;
    }

    public function shipPostalCode(string $value): self
    {
        $this->data['shipAddrPostCode'] = trim($value);
        return $this;
    }

    public function shipState(string $value): self
    {
        $this->data['shipAddrState'] = trim($value);
        return $this;
    }

    public function addressMatchesShipping(bool $matches = true): self
    {
        $this->data['addrMatch'] = $matches ? 'Y' : 'N';
        return $this;
    }

    /** @deprecated 2.2.0 Use addressMatchesShipping(). */
    public function addrMatch(bool $value): self
    {
        return $this->addressMatchesShipping($value);
    }

    public function mobilePhone(string $cc, string $subscriber): self
    {
        $this->data['mobilePhone'] = $this->phone($cc, $subscriber);
        return $this;
    }

    public function workPhone(string $cc, string $subscriber): self
    {
        $this->data['workPhone'] = $this->phone($cc, $subscriber);
        return $this;
    }

    public function accountId(string $value): self
    {
        $this->data['acctID'] = trim($value);
        return $this;
    }

    /** @deprecated 2.2.0 Use accountId(). */
    public function acctID(string $value): self
    {
        return $this->accountId($value);
    }

    /** @param array<string, mixed> $info */
    public function accountInfo(array $info): self
    {
        $this->data['acctInfo'] = array_filter(
            array_merge([
                'chAccAgeInd' => '01',
                'chAccChange' => '',
                'chAccDate' => '',
                'chAccPwChange' => '',
                'chAccPwChangeInd' => '01',
                'suspiciousAccActivity' => '01',
            ], $info),
            static fn(mixed $value): bool => $value !== null && $value !== '',
        );

        return $this;
    }

    /**
     * @param array<string, mixed> $info
     *
     * @deprecated 2.2.0 Use accountInfo().
     */
    public function acctInfo(array $info): self
    {
        return $this->accountInfo($info);
    }

    public function suspicious(bool $suspicious = true): self
    {
        $info = $this->data['acctInfo'];
        $info['suspiciousAccActivity'] = $suspicious ? '02' : '01';

        return $this->accountInfo($info);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter(
            $this->data,
            static fn(mixed $value): bool =>
                $value !== null && $value !== '' && $value !== [],
        );
    }

    /**
     * Create billing information from an application-specific user array.
     *
     * @param array<string, mixed> $user User data.
     *
     * @deprecated 2.2.0 Map application data explicitly with Billing::from().
     */
    public static function fromUser(array $user): self
    {
        return self::from([
            'email' => $user['email'] ?? '',
            'country' => $user['country'] ?? '132',
            'city' => $user['city'] ?? '',
            'address' => $user['address'] ?? '',
            'address2' => $user['address2'] ?? '',
            'address3' => $user['address3'] ?? '',
            'postalCode' => $user['postCode'] ?? '',
            'state' => $user['state'] ?? '',
            'mobilePhone' => [
                'cc' => $user['mobilePhoneCC'] ?? '238',
                'subscriber' => $user['mobilePhone'] ?? '',
            ],
            'workPhone' => [
                'cc' => $user['workPhoneCC'] ?? '238',
                'subscriber' => $user['workPhone'] ?? '',
            ],
            'accountId' => (string) ($user['id'] ?? ''),
            'accountInfo' => [
                'chAccAgeInd' => $user['chAccAgeInd'] ?? '05',
                'chAccChange' => self::dateValue($user['updated_at'] ?? null),
                'chAccDate' => self::dateValue($user['created_at'] ?? null),
                'chAccPwChange' => self::dateValue($user['updated_at'] ?? null),
                'chAccPwChangeInd' => $user['chAccPwInd'] ?? '05',
            ],
            'suspicious' => (bool) ($user['suspicious'] ?? false),
        ]);
    }

    /** @return array{cc: string, subscriber: string}|null */
    private function normalizePhone(mixed $value): ?array
    {
        if (is_string($value) || is_int($value)) {
            return $this->phone('238', (string) $value);
        }

        if (!is_array($value)) {
            return null;
        }

        return $this->phone(
            (string) ($value['cc'] ?? '238'),
            (string) ($value['subscriber'] ?? ''),
        );
    }

    /** @return array{cc: string, subscriber: string}|null */
    private function phone(string $cc, string $subscriber): ?array
    {
        $cc = preg_replace('/\D+/', '', $cc) ?? '';
        $subscriber = preg_replace('/\D+/', '', $subscriber) ?? '';

        if ($subscriber === '') {
            return null;
        }

        return [
            'cc' => $cc !== '' ? $cc : '238',
            'subscriber' => $subscriber,
        ];
    }

    private static function dateValue(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('Ymd', $timestamp);
    }
}
