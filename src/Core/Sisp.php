<?php

declare(strict_types=1);

namespace Erilshk\Sisp\Core;

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;

/**
 * Base processor for Vinti4Net payment and refund operations.
 */
abstract class Sisp
{
    public const DEFAULT_BASE_URL =
    'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment';

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND = '4';

    public const CURRENCY_CVE = '132';
    public const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    protected string $posID;
    protected string $posAuthCode;
    protected string $baseUrl;

    abstract protected function fingerprintRequest(array $data): string;

    abstract protected function fingerprintResponse(array $data): string;

    /** @return array{fields: array<string, mixed>, postUrl: string} */
    abstract public function preparePayment(array $params): array;

    /**
     * Create a SISP transaction processor.
     *
     * @param string      $posID       POS identifier provided by SISP.
     * @param string      $posAuthCode POS authorization code provided by SISP.
     * @param string|null $endpoint    Optional custom gateway endpoint.
     */
    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null,
    ) {
        $posID = trim($posID);
        $posAuthCode = trim($posAuthCode);

        if ($posID === '') {
            throw new Vinti4Exception(
                'O POS ID não pode estar vazio.'
            );
        }

        if (trim($posAuthCode) === '') {
            throw new Vinti4Exception(
                'O código de autenticação não pode estar vazio.'
            );
        }

        if (
            $endpoint !== null &&
            filter_var($endpoint, FILTER_VALIDATE_URL) === false
        ) {
            throw new Vinti4Exception(
                'O endpoint da SISP deve ser uma URL válida.'
            );
        }

        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    /**
     * Process and validate a callback sent by SISP.
     *
     * @param array<string, mixed> $postData Raw callback payload.
     *
     * @return array{
     *     success: bool,
     *     fingerprint_valid: bool,
     *     message_type: string,
     *     data: array<string, mixed>
     * }
     */
    public function processResponse(array $postData): array
    {
        if ($postData === []) {
            throw new Vinti4Exception(
                'A resposta da SISP está vazia.'
            );
        }

        $messageType = (string) ($postData['messageType'] ?? '');
        $successType = in_array(
            $messageType,
            self::SUCCESS_MESSAGE_TYPES,
            true,
        );

        // The response fingerprint formula applies only to successful
        // transaction message types. Error and cancellation payloads must
        // never be promoted to success, but they do not use that formula.
        $fingerprintValid = true;

        if ($successType) {
            $expected = $this->fingerprintResponse($postData);
            $received = trim((string) ($postData['resultFingerPrint'] ?? ''));
            $fingerprintValid = $received !== ''
                && hash_equals($expected, $received);
        }

        return [
            'success' => $successType && $fingerprintValid,
            'fingerprint_valid' => $fingerprintValid,
            'message_type' => $messageType,
            'data' => $postData,
        ];
    }

    /**
     * Convert an ISO currency name or numeric code to the SISP code.
     */
    protected function currencyToCode(string $currency): int
    {
        $currency = strtoupper(trim($currency));

        return match ($currency) {
            'CVE' => 132,
            'USD' => 840,
            'EUR' => 978,
            'BRL' => 986,
            'GBP' => 826,
            'JPY' => 392,
            default => preg_match('/^\d{3}$/', $currency)
                ? (int) $currency
                : throw new Vinti4Exception(
                    "Moeda inválida: {$currency}."
                ),
        };
    }

    /**
     * Normalize a request amount while preserving the v2 input contract.
     *
     * Values such as `1500`, `"1500"` and `"1500.00"` are accepted.
     */
    protected function normalizeRequestAmount(int|float|string $amount): string
    {
        $value = trim((string) $amount);

        if (!preg_match('/^[1-9]\d{0,12}$/', $value)) {
            throw new Vinti4Exception(
                'Amount deve ser um inteiro positivo com no máximo 13 dígitos.'
            );
        }

        return $value;
    }

    /**
     * Convert a SISP amount to its fingerprint representation without floats.
     */
    protected function amountToLong(int|float|string|null $amount): string
    {
        $value = trim((string) ($amount ?? '0'));

        if (!preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            throw new Vinti4Exception(
                'O valor da resposta da SISP possui formato inválido.'
            );
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = str_pad($matches[2] ?? '', 3, '0');
        $result = ltrim($integer . $fraction, '0');

        return $result === '' ? '0' : $result;
    }

    /**
     * Return the Base64-encoded SHA-512 authorization code.
     */
    protected function encodedAuthCode(): string
    {
        return base64_encode(hash('sha512', $this->posAuthCode, true));
    }

    /**
     * Normalize Billing instances, SISP fields and legacy `user` data.
     *
     * @param array<string, mixed> $billing
     *
     * @return array<string, mixed>
     */
    protected function normalizeBilling(array $billing): array
    {
        $user = $billing['user'] ?? [];
        unset($billing['user']);

        if (is_object($user)) {
            $user = get_object_vars($user);
        }

        $legacy = is_array($user) && $user !== []
            ? Billing::fromUser($user)->toArray()
            : [];

        $explicit = Billing::from($billing)->toArray();
        $normalized = array_replace($legacy, $explicit);

        if (isset($legacy['acctInfo'], $explicit['acctInfo'])) {
            $normalized['acctInfo'] = array_replace(
                $legacy['acctInfo'],
                $explicit['acctInfo'],
            );
        }

        return $normalized;
    }

    /**
     * Generate the Base64-encoded 3D Secure purchase request.
     *
     * @param array<string, mixed> $billing
     */
    protected function generatePurchaseRequest(array $billing): string
    {
        $billing = $this->normalizeBilling($billing);
        $required = [
            'email',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
        ];

        $missing = array_filter(
            $required,
            static fn(string $field): bool =>
            !isset($billing[$field]) || trim((string) $billing[$field]) === '',
        );

        if ($missing !== []) {
            throw new Vinti4Exception(
                'Campos obrigatórios ausentes em billing: ' .
                    implode(', ', $missing) . '.'
            );
        }

        try {
            $json = json_encode(
                $billing,
                JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE |
                    JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new Vinti4Exception(
                'Erro ao gerar JSON de billing.',
                0,
                $exception,
            );
        }

        return base64_encode($json);
    }

    /**
     * Validate common payment and refund fields.
     *
     * @param array<string, mixed> $params
     */
    protected function validateParams(array $params): ?string
    {
        $transactionCode = (string) ($params['transactionCode'] ?? '');

        if (!in_array($transactionCode, ['1', '2', '3', '4'], true)) {
            return 'TransactionCode inválido. Valores permitidos: 1,2,3,4.';
        }

        $merchantRef = trim((string) ($params['merchantRef'] ?? ''));
        if ($merchantRef === '' || strlen($merchantRef) > 15) {
            return 'MerchantRef é obrigatório e deve ter no máximo 15 caracteres.';
        }

        $merchantSession = trim((string) ($params['merchantSession'] ?? ''));
        if ($merchantSession === '' || strlen($merchantSession) > 15) {
            return 'MerchantSession é obrigatório e deve ter no máximo 15 caracteres.';
        }

        if (in_array($transactionCode, ['2', '3'], true)) {
            if (!preg_match('/^\d+$/', (string) ($params['entityCode'] ?? ''))) {
                return 'EntityCode é obrigatório e deve ser numérico.';
            }

            if (!preg_match('/^\d{1,9}$/', (string) ($params['referenceNumber'] ?? ''))) {
                return 'ReferenceNumber é obrigatório e deve ter até 9 dígitos.';
            }
        }

        $amount = (string) ($params['amount'] ?? '');
        if (!preg_match('/^[1-9]\d{0,12}$/', $amount)) {
            return 'Amount deve ser um inteiro positivo com até 13 dígitos.';
        }

        $currency = (string) ($params['currency'] ?? '');
        if (!preg_match('/^\d{3}$/', $currency)) {
            return 'Currency deve ser um código numérico ISO 4217 de 3 dígitos.';
        }


        if ($transactionCode === self::TRANSACTION_TYPE_REFUND && $currency !== self::CURRENCY_CVE) {
            return "Currency para estorno deve ser '132' (CVE).";
        }


        if (filter_var($params['urlMerchantResponse'] ?? null, FILTER_VALIDATE_URL) === false) {
            return 'UrlMerchantResponse deve ser uma URL válida.';
        }

        $language = strtolower((string) ($params['languageMessages'] ?? ''));
        if (!in_array($language, ['pt', 'en', 'fr'], true)) {
            return "LanguageMessages deve ser 'pt', 'en' ou 'fr'.";
        }

        if ($transactionCode === self::TRANSACTION_TYPE_REFUND) {
            if (!preg_match('/^\d{1,4}$/', (string) ($params['clearingPeriod'] ?? ''))) {
                return 'ClearingPeriod deve ter até 4 dígitos numéricos.';
            }

            if (!preg_match('/^[A-Za-z0-9_]{1,8}$/', (string) ($params['transactionID'] ?? ''))) {
                return 'TransactionID deve ter até 8 caracteres alfanuméricos.';
            }
        }

        if (isset($params['acctID']) && strlen((string) $params['acctID']) > 64) {
            return 'AcctID deve ter no máximo 64 caracteres.';
        }

        return null;
    }
}
