<?php

declare(strict_types=1);

namespace Eril\Sisp;

use Eril\Sisp\Exception\InvalidRequestException;

final class Billing
{
    /** @var array<string, mixed> */
    private array $data = [
        'billAddrCountry' => '132',
    ];

    private const ACCOUNT_INFO_FIELDS = [
        'chAccAgeInd',
        'chAccChange',
        'chAccDate',
        'chAccPwChange',
        'chAccPwChangeInd',
        'suspiciousAccActivity',
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
     * Create billing information from an array.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidRequestException
     */
    public static function from(array $data): self
    {
        $billing = self::make();

        foreach ($data as $field => $value) {
            match ($field) {
                'email' =>
                    $billing->email((string) $value),

                'country', 'billAddrCountry' =>
                    $billing->country((string) $value),

                'city', 'billAddrCity' =>
                    $billing->city((string) $value),

                'address', 'billAddrLine1' =>
                    $billing->address((string) $value),

                'address2', 'billAddrLine2' =>
                    $billing->address2((string) $value),

                'address3', 'billAddrLine3' =>
                    $billing->address3((string) $value),

                'postalCode', 'billAddrPostCode' =>
                    $billing->postalCode((string) $value),

                'state', 'billAddrState' =>
                    $billing->state((string) $value),

                'shipCountry', 'shipAddrCountry' =>
                    $billing->shipCountry((string) $value),

                'shipCity', 'shipAddrCity' =>
                    $billing->shipCity((string) $value),

                'shipAddress', 'shipAddrLine1' =>
                    $billing->shipAddress((string) $value),

                'shipPostalCode', 'shipAddrPostCode' =>
                    $billing->shipPostalCode((string) $value),

                'shipState', 'shipAddrState' =>
                    $billing->shipState((string) $value),

                'addrMatch' =>
                    $billing->setAddressMatch($value),

                'mobilePhone', 'phone' =>
                    $billing->setPhone('mobilePhone', $value),

                'workPhone' =>
                    $billing->setPhone('workPhone', $value),

                'acctID', 'accountId' =>
                    $billing->accountId((string) $value),

                'acctInfo', 'accountInfo' =>
                    $billing->accountInfo(
                        self::requireArray($field, $value)
                    ),

                'suspicious' =>
                    $billing->suspicious(
                        self::requireBoolean($field, $value)
                    ),

                default => throw new InvalidRequestException(
                    "Campo de billing não permitido: {$field}."
                ),
            };
        }

        return $billing;
    }

    /**
     * Set the cardholder email address.
     */
    public function email(string $email): self
    {
        $email = trim($email);

        if (
            $email !== '' &&
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
        ) {
            throw new InvalidRequestException(
                'O email do billing é inválido.'
            );
        }

        $this->data['email'] = $email;

        return $this;
    }

    /**
     * Set the numeric ISO 3166-1 billing country code.
     */
    public function country(string $country): self
    {
        $country = trim($country);

        if ($country !== '' && !preg_match('/^\d{3}$/', $country)) {
            throw new InvalidRequestException(
                'O país do billing deve usar um código numérico de 3 dígitos.'
            );
        }

        $this->data['billAddrCountry'] = $country;

        return $this;
    }

    /**
     * Set the billing city.
     */
    public function city(string $city): self
    {
        $this->data['billAddrCity'] = trim($city);

        return $this;
    }

    /**
     * Set the primary billing address.
     */
    public function address(string $address): self
    {
        $this->data['billAddrLine1'] = trim($address);

        return $this;
    }

    /**
     * Set the secondary billing address.
     */
    public function address2(string $address): self
    {
        $this->data['billAddrLine2'] = trim($address);

        return $this;
    }

    /**
     * Set the third billing address line.
     */
    public function address3(string $address): self
    {
        $this->data['billAddrLine3'] = trim($address);

        return $this;
    }

    /**
     * Set the billing postal code.
     */
    public function postalCode(string $postalCode): self
    {
        $this->data['billAddrPostCode'] = trim($postalCode);

        return $this;
    }

    /**
     * Set the billing state or region.
     */
    public function state(string $state): self
    {
        $this->data['billAddrState'] = trim($state);

        return $this;
    }

    /**
     * Set the shipping country code.
     */
    public function shipCountry(string $country): self
    {
        $country = trim($country);

        if ($country !== '' && !preg_match('/^\d{3}$/', $country)) {
            throw new InvalidRequestException(
                'O país de entrega deve usar um código numérico de 3 dígitos.'
            );
        }

        $this->data['shipAddrCountry'] = $country;

        return $this;
    }

    /**
     * Set the shipping city.
     */
    public function shipCity(string $city): self
    {
        $this->data['shipAddrCity'] = trim($city);

        return $this;
    }

    /**
     * Set the shipping address.
     */
    public function shipAddress(string $address): self
    {
        $this->data['shipAddrLine1'] = trim($address);

        return $this;
    }

    /**
     * Set the shipping postal code.
     */
    public function shipPostalCode(string $postalCode): self
    {
        $this->data['shipAddrPostCode'] = trim($postalCode);

        return $this;
    }

    /**
     * Set the shipping state or region.
     */
    public function shipState(string $state): self
    {
        $this->data['shipAddrState'] = trim($state);

        return $this;
    }

    /**
     * Indicate whether billing and shipping addresses match.
     */
    public function addressMatchesShipping(bool $matches): self
    {
        $this->data['addrMatch'] = $matches ? 'Y' : 'N';

        return $this;
    }

    /**
     * Set the cardholder mobile phone.
     */
    public function mobilePhone(
        string $countryCode,
        string $subscriber,
    ): self {
        $this->data['mobilePhone'] = $this->phone(
            $countryCode,
            $subscriber,
        );

        return $this;
    }

    /**
     * Set the cardholder work phone.
     */
    public function workPhone(
        string $countryCode,
        string $subscriber,
    ): self {
        $this->data['workPhone'] = $this->phone(
            $countryCode,
            $subscriber,
        );

        return $this;
    }

    /**
     * Set the cardholder account identifier.
     */
    public function accountId(string $accountId): self
    {
        $accountId = trim($accountId);

        if (strlen($accountId) > 64) {
            throw new InvalidRequestException(
                'O identificador da conta deve ter no máximo 64 caracteres.'
            );
        }

        $this->data['acctID'] = $accountId;

        return $this;
    }

    /**
     * Set the cardholder 3DS account information.
     *
     * @param array<string, mixed> $info
     */
    public function accountInfo(array $info): self
    {
        foreach ($info as $field => $value) {
            if (!in_array($field, self::ACCOUNT_INFO_FIELDS, true)) {
                throw new InvalidRequestException(
                    "Campo de accountInfo não permitido: {$field}."
                );
            }

            $this->data['acctInfo'][$field] = trim(
                (string) $value
            );
        }

        return $this;
    }

    /**
     * Mark whether the account has suspicious activity.
     */
    public function suspicious(bool $suspicious = true): self
    {
        $this->data['acctInfo']['suspiciousAccActivity'] =
            $suspicious ? '02' : '01';

        return $this;
    }

    /**
     * Return normalized SISP billing fields.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter(
            $this->data,
            static fn(mixed $value): bool =>
                $value !== null &&
                $value !== '' &&
                $value !== [],
        );
    }

    /**
     * Set a phone field from an array or subscriber string.
     *
     * @throws InvalidRequestException
     */
    private function setPhone(
        string $field,
        mixed $value,
    ): self {
        if (is_string($value) || is_int($value)) {
            $phone = $this->phone('238', (string) $value);
        } elseif (
            is_array($value) &&
            isset($value['cc'], $value['subscriber'])
        ) {
            $phone = $this->phone(
                (string) $value['cc'],
                (string) $value['subscriber'],
            );
        } else {
            throw new InvalidRequestException(
                "O campo {$field} deve ser um número ou conter cc e subscriber."
            );
        }

        $this->data[$field] = $phone;

        return $this;
    }

    /**
     * Normalize a phone number.
     *
     * @return array{cc: string, subscriber: string}
     */
    private function phone(
        string $countryCode,
        string $subscriber,
    ): array {
        $countryCode = preg_replace('/\D+/', '', $countryCode);
        $subscriber = preg_replace('/\D+/', '', $subscriber);

        if ($countryCode === '' || $subscriber === '') {
            throw new InvalidRequestException(
                'O telefone deve conter código do país e número.'
            );
        }

        return [
            'cc' => $countryCode,
            'subscriber' => $subscriber,
        ];
    }

    /**
     * Set addrMatch from a boolean or Y/N value.
     */
    private function setAddressMatch(mixed $value): self
    {
        if (is_bool($value)) {
            return $this->addressMatchesShipping($value);
        }

        $value = strtoupper(trim((string) $value));

        if (!in_array($value, ['Y', 'N'], true)) {
            throw new InvalidRequestException(
                "AddrMatch deve ser booleano, 'Y' ou 'N'."
            );
        }

        $this->data['addrMatch'] = $value;

        return $this;
    }

    /**
     * Require an array field.
     *
     * @return array<string, mixed>
     */
    private static function requireArray(
        string $field,
        mixed $value,
    ): array {
        if (!is_array($value)) {
            throw new InvalidRequestException(
                "O campo {$field} deve ser um array."
            );
        }

        return $value;
    }

    /**
     * Require a boolean field.
     */
    private static function requireBoolean(
        string $field,
        mixed $value,
    ): bool {
        if (!is_bool($value)) {
            throw new InvalidRequestException(
                "O campo {$field} deve ser booleano."
            );
        }

        return $value;
    }
}