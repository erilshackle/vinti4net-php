<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

use Erilshk\Sisp\Exceptions\Vinti4Exception;

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

    /** Prevent direct construction; use make() or from(). */
    private function __construct() {}

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
     * Friendly names and their SISP equivalents are both accepted. Unknown keys
     * are ignored for backward compatibility. The billing country defaults to
     * "132" (Cabo Verde).
     *
     * When billing is used, supply email, city and address. The country defaults
     * to 132 and an unknown postal code defaults to 0000 at request creation.
     *
     * Phone numbers may be given as a local subscriber number or as an array
     * with separate country code and subscriber number.
     *
     * @param array{
     *     email?: string,
     *     country?: string,
     *     billAddrCountry?: string,
     *     city?: string,
     *     billAddrCity?: string,
     *     address?: string,
     *     billAddrLine1?: string,
     *     address2?: string,
     *     billAddrLine2?: string,
     *     address3?: string,
     *     billAddrLine3?: string,
     *     postalCode?: string,
     *     billAddrPostCode?: string,
     *     state?: string,
     *     billAddrState?: string,
     *     shipCountry?: string,
     *     shipAddrCountry?: string,
     *     shipCity?: string,
     *     shipAddrCity?: string,
     *     shipAddress?: string,
     *     shipAddrLine1?: string,
     *     shipPostalCode?: string,
     *     shipAddrPostCode?: string,
     *     shipState?: string,
     *     shipAddrState?: string,
     *     phone?: string|int|array{cc?: string|int, subscriber?: string|int},
     *     mobilePhone?: string|int|array{cc?: string|int, subscriber?: string|int},
     *     workPhone?: string|int|array{cc?: string|int, subscriber?: string|int},
     *     accountId?: string,
     *     acctID?: string,
     *     accountInfo?: array<string, mixed>,
     *     acctInfo?: array<string, mixed>,
     *     addressMatchesShipping?: bool|'Y'|'N'|'y'|'n',
     *     addrMatch?: bool|string,
     *     suspicious?: bool
     * } $data Billing and 3D Secure customer data.
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
                $this->addressMatchesShipping($value);
                continue;
            }

            $this->data[$field] = is_string($value) ? trim($value) : $value;
        }

        return $this;
    }

    /** Set the cardholder email required when billing is supplied. */
    public function email(string $value): self
    {
        $this->data['email'] = trim($value);
        return $this;
    }

    /** Set the billing country code; defaults to "132" (Cabo Verde). */
    public function country(string $value): self
    {
        $this->data['billAddrCountry'] = trim($value);
        return $this;
    }

    /** Set the billing city required when billing is supplied. */
    public function city(string $value): self
    {
        $this->data['billAddrCity'] = trim($value);
        return $this;
    }

    /** Set the first billing address line required when billing is supplied. */
    public function address(string $value): self
    {
        $this->data['billAddrLine1'] = trim($value);
        return $this;
    }

    /** Set the optional second billing address line. */
    public function address2(string $value): self
    {
        $this->data['billAddrLine2'] = trim($value);
        return $this;
    }

    /** Set the optional third billing address line. */
    public function address3(string $value): self
    {
        $this->data['billAddrLine3'] = trim($value);
        return $this;
    }

    /** Set the billing postal code; an unknown/blank code uses 0000 in the request. */
    public function postalCode(string $value): self
    {
        $this->data['billAddrPostCode'] = trim($value);
        return $this;
    }

    /** Set the billing state or region. */
    public function state(string $value): self
    {
        $this->data['billAddrState'] = trim($value);
        return $this;
    }

    /** Set the shipping country code. */
    public function shipCountry(string $value): self
    {
        $this->data['shipAddrCountry'] = trim($value);
        return $this;
    }

    /** Set the shipping city. */
    public function shipCity(string $value): self
    {
        $this->data['shipAddrCity'] = trim($value);
        return $this;
    }

    /** Set the first shipping address line. */
    public function shipAddress(string $value): self
    {
        $this->data['shipAddrLine1'] = trim($value);
        return $this;
    }

    /** Set the shipping postal code. */
    public function shipPostalCode(string $value): self
    {
        $this->data['shipAddrPostCode'] = trim($value);
        return $this;
    }

    /** Set the shipping state or region. */
    public function shipState(string $value): self
    {
        $this->data['shipAddrState'] = trim($value);
        return $this;
    }

    /**
     * Set addrMatch; Y copies billing fields to shipping when exported.
     * @param bool|'Y'|'N'|'y'|'n' $matches Address correspondence.
     */
    public function addressMatchesShipping(bool|string $matches = true): self
    {
        $value = is_bool($matches) ? ($matches ? 'Y' : 'N') : strtoupper(trim($matches));
        if (!in_array($value, ['Y', 'N'], true)) {
            throw new Vinti4Exception('addrMatch deve ser Y, N ou booleano.');
        }
        $this->data['addrMatch'] = $value;
        return $this;
    }

    /**
     * Set whether billing and shipping addresses match.
     *
     * @param bool|'Y'|'N'|'y'|'n' $value Address correspondence.
     * @deprecated 2.2.0 Use addressMatchesShipping().
     */
    public function addrMatch(bool|string $value): self
    {
        return $this->addressMatchesShipping($value);
    }

    /**
     * Set the mobile phone with country code and local subscriber separately.
     *
     * Non-digits are stripped; a blank subscriber omits the phone number.
     *
     * @param string $cc Country calling code, e.g. "238".
     * @param string $subscriber Local subscriber number, without the country code.
     */
    public function mobilePhone(string $cc, string $subscriber): self
    {
        $this->data['mobilePhone'] = $this->phone($cc, $subscriber);
        return $this;
    }

    /**
     * Set the work phone with country code and local subscriber separately.
     *
     * @param string $cc Country calling code, e.g. "238".
     * @param string $subscriber Local subscriber number, without the country code.
     */
    public function workPhone(string $cc, string $subscriber): self
    {
        $this->data['workPhone'] = $this->phone($cc, $subscriber);
        return $this;
    }

    /** Set the merchant user ID (acctID); the login email may also be used. */
    public function accountId(string $value): self
    {
        $this->data['acctID'] = trim($value);
        return $this;
    }

    /**
     * Set the cardholder account identifier.
     *
     * @deprecated 2.2.0 Use accountId().
     */
    public function acctID(string $value): self
    {
        return $this->accountId($value);
    }

    /**
     * Set 3D Secure account information using SISP acctInfo field names.
     *
     * Missing age/password indicators are omitted; suspicious activity defaults
     * to 01. Dates use YYYYMMDD. Existing account fields are preserved.
     *
     * @param array{
     *   chAccAgeInd?: '01'|'02'|'03'|'04'|'05',
     *   chAccChange?: string,
     *   chAccDate?: string,
     *   chAccPwChange?: string,
     *   chAccPwChangeInd?: '01'|'02'|'03'|'04'|'05',
     *   suspiciousAccActivity?: '01'|'02',
     * } $info Age/password: 01 no account, 02 during checkout, 03 <30 days,
     *         04 30–60 days, 05 >60 days. Dates: profile change, creation,
     *         password change. Suspicious activity: 01 no, 02 yes.
     *         Age 01 is reserved for SISP-approved guest-account exceptions.
     */
    public function accountInfo(array $info): self
    {
        $this->data['acctInfo'] = array_filter(
            array_merge([
                'chAccChange' => '',
                'chAccDate' => '',
                'chAccPwChange' => '',
                'suspiciousAccActivity' => '01',
            ], $this->data['acctInfo'], $info),
            static fn(mixed $value): bool => $value !== null && $value !== '',
        );

        return $this;
    }

    /**
     * Set account data with named arguments instead of an application user array.
     *
     * @param string $id Account ID (up to 64 characters).
     * @param string|null $createdAt Account creation date, YYYYMMDD.
     * @param string|null $changedAt Last profile change, YYYYMMDD.
     * @param string|null $passwordChangedAt Last password change, YYYYMMDD.
     * @param '01'|'02'|'03'|'04'|'05'|null $ageIndicator Account age; see accountInfo().
     * @param '01'|'02'|'03'|'04'|'05'|null $passwordChangeIndicator Password age; see accountInfo().
     * @param bool $suspicious Known suspicious account activity.
     */
    public function account(
        string $id,
        ?string $createdAt = null,
        ?string $changedAt = null,
        ?string $passwordChangedAt = null,
        ?string $ageIndicator = null,
        ?string $passwordChangeIndicator = null,
        bool $suspicious = false,
    ): self {
        return $this->accountId($id)->accountInfo(array_filter([
            'chAccDate' => $createdAt,
            'chAccChange' => $changedAt,
            'chAccPwChange' => $passwordChangedAt,
            'chAccAgeInd' => $ageIndicator,
            'chAccPwChangeInd' => $passwordChangeIndicator,
            'suspiciousAccActivity' => $suspicious ? '02' : '01',
        ], static fn(mixed $value): bool => $value !== null));
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

    /** Set the account suspicious-activity indicator (02 if true, 01 otherwise). */
    public function suspicious(bool $suspicious = true): self
    {
        $info = $this->data['acctInfo'];
        $info['suspiciousAccActivity'] = $suspicious ? '02' : '01';

        return $this->accountInfo($info);
    }

    /**
     * Return SISP fields with line 2 fallback and matching shipping addresses.
     * Empty strings, nulls and empty arrays are omitted; postal fallback is applied in the request.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = $this->data;
        if (!empty($data['billAddrLine1']) && empty($data['billAddrLine2'])) {
            $data['billAddrLine2'] = $data['billAddrLine1'];
        }
        if (($data['addrMatch'] ?? null) === 'Y') {
            foreach (['Country', 'City', 'Line1', 'PostCode', 'State'] as $suffix) {
                $data['shipAddr' . $suffix] = $data['billAddr' . $suffix];
            }
        }

        return array_filter(
            $data,
            static fn(mixed $value): bool =>
            $value !== null && $value !== '' && $value !== [],
        );
    }

    /**
     * Create billing information from an application-specific user array.
     *
     * @param array<string, mixed> $user User data.
     *
     * @deprecated 2.2.0 Use account() for account fields and from() for explicit billing mappings.
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
                'chAccAgeInd' => $user['chAccAgeInd'] ?? null,
                'chAccChange' => self::dateValue($user['updated_at'] ?? null),
                'chAccDate' => self::dateValue($user['created_at'] ?? null),
                'chAccPwChange' => self::dateValue($user['password_changed_at'] ?? null),
                'chAccPwChangeInd' => $user['chAccPwInd'] ?? null,
            ],
            'suspicious' => (bool) ($user['suspicious'] ?? false),
        ]);
    }

    /**
     * Normalize a local phone or a {cc, subscriber} pair.
     *
     * Plain strings use calling code 238; international prefixes are not parsed.
     *
     * @return array{cc: string, subscriber: string}|null
     */
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

    /**
     * Strip non-digits and omit a phone without a subscriber number.
     *
     * @return array{cc: string, subscriber: string}|null
     */
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

    /** Convert a legacy user date to YYYYMMDD, or return an empty string. */
    private static function dateValue(mixed $value): string
    {
        if (!is_string($value) || trim($value) === '') {
            return '';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? '' : date('Ymd', $timestamp);
    }
}
