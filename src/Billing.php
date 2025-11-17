<?php

namespace Erilshk\Vinti4Net;

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

     /**
     * Private constructor to enforce the builder pattern.
     */
    private function __construct() {}

    /**
     * Creates a new empty Billing instance.
     *
     * @return self
     */
    public static function make(): self
    {
        return new self();
    }

    /**
     * Creates billing data from an input array and returns the normalized array.
     *
     * @param array $data Input billing fields using mixed naming formats.
     * @return array Normalized billing data ready for SISP.
     */
    public static function create(array $data): array
    {
        return self::make()
            ->fill($data)
            ->toArray();
    }

    /**
     * Fills the billing data using a key-mapping table that supports
     * multiple user-friendly field names.
     *
     * @param array $data Input values using flexible naming (e.g., "city", "billAddrCity", etc.).
     * @return self
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

    /**
     * Sets the customer email.
     *
     * @param string $v
     * @return self
     */
    public function email(string $v): self { $this->data['email'] = $v; return $this; }
     /**
     * Sets the billing country code (ISO numeric or SISP-specific).
     *
     * @param string $v
     * @return self
     */
    public function country(string $v): self
    {
        $this->data['billAddrCountry'] = $v;
        return $this;
    }

    /**
     * Sets the billing city.
     *
     * @param string $v
     * @return self
     */
    public function city(string $v): self
    {
        $this->data['billAddrCity'] = $v;
        return $this;
    }

    /**
     * Sets the primary address line.
     *
     * @param string $v
     * @return self
     */
    public function address(string $v): self
    {
        $this->data['billAddrLine1'] = $v;
        return $this;
    }

    /**
     * Sets the secondary address line.
     *
     * @param string $v
     * @return self
     */
    public function address2(string $v): self
    {
        $this->data['billAddrLine2'] = $v;
        return $this;
    }

    /**
     * Sets the postal code.
     *
     * @param string $v
     * @return self
     */
    public function postalCode(string $v): self
    {
        $this->data['billAddrPostCode'] = $v;
        return $this;
    }

    /**
     * Sets the mobile phone number.
     * Non-digit characters are automatically stripped.
     *
     * @param string $v
     * @return self
     */
    public function mobilePhone(string $v): self
    {
        $this->data['mobilePhone'] = $this->cleanPhone($v);
        return $this;
    }

    /**
     * Removes non-numeric characters from a phone number.
     *
     * @param string|null $phone
     * @return string Clean numeric phone value.
     */
    private function cleanPhone(?string $phone): string
    {
        if (!$phone) return '';
        return preg_replace('/\D+/', '', $phone);
    }

    /**
     * Returns the normalized billing data as an array.
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->data;
    }
}
