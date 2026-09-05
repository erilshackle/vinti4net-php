<?php

declare(strict_types=1);

namespace Eril\Sisp\Core;

use Eril\Sisp\Exception\InvalidConfigurationException;
use Eril\Sisp\Exception\InvalidRequestException;
use Eril\Sisp\Exception\InvalidResponseException;
use JsonException;

abstract class Sisp
{
    public const DEFAULT_BASE_URL =
    'https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment';

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND = '4';

    public const CURRENCY_CVE = '132';

    public const SUCCESS_MESSAGE_TYPES = [
        '8',
        '10',
        'P',
        'M',
    ];

    protected readonly string $posID;
    protected readonly string $posAuthCode;
    protected readonly string $baseUrl;

    /**
     * Create a SISP transaction processor.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null,
    ) {
        $posID = trim($posID);
        $posAuthCode = trim($posAuthCode);
        $endpoint = $endpoint !== null
            ? trim($endpoint)
            : self::DEFAULT_BASE_URL;

        if ($posID === '') {
            throw new InvalidConfigurationException(
                'O POS ID não pode estar vazio.'
            );
        }

        if ($posAuthCode === '') {
            throw new InvalidConfigurationException(
                'O código de autenticação não pode estar vazio.'
            );
        }

        if (filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new InvalidConfigurationException(
                'O endpoint da SISP deve ser uma URL válida.'
            );
        }

        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = rtrim($endpoint, '?');
    }

    /**
     * Generate the request fingerprint.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function fingerprintRequest(array $data): string;

    /**
     * Generate the expected response fingerprint.
     *
     * @param array<string, mixed> $data
     */
    abstract protected function fingerprintResponse(array $data): string;

    /**
     * Prepare a transaction for submission to SISP.
     *
     * @param array<string, mixed> $params
     *
     * @return array{
     *     postUrl: string,
     *     fields: array<string, mixed>
     * }
     */
    abstract public function preparePayment(array $params): array;

    /**
     * Process and validate a response returned by SISP.
     *
     * @param array<string, mixed> $postData
     *
     * @return array{
     *     success: bool,
     *     fingerprint_valid: bool,
     *     message_type: string,
     *     data: array<string, mixed>
     * }
     *
     * @throws InvalidResponseException
     */
    public function processResponse(array $postData): array
    {
        if ($postData === []) {
            throw new InvalidResponseException(
                'A resposta da SISP está vazia.'
            );
        }

        $received = $postData['resultFingerPrint'] ?? null;

        if (!is_string($received) || trim($received) === '') {
            throw new InvalidResponseException(
                'A resposta da SISP não contém uma fingerprint.'
            );
        }

        $messageType = (string) ($postData['messageType'] ?? '');

        if ($messageType === '') {
            throw new InvalidResponseException(
                'A resposta da SISP não contém o tipo da mensagem.'
            );
        }

        $expected = $this->fingerprintResponse($postData);
        $fingerprintValid = hash_equals($expected, $received);

        return [
            'success' => $fingerprintValid && in_array(
                $messageType,
                self::SUCCESS_MESSAGE_TYPES,
                true,
            ),
            'fingerprint_valid' => $fingerprintValid,
            'message_type' => $messageType,
            'data' => $postData,
        ];
    }

    /**
     * Generates a merchant session accepted by the SISP gateway.
     *
     * The returned value always contains exactly 15 characters:
     * one prefix character followed by the current date and time.
     */
    final public static function generateSession(): string
    {
        return 'S' . date('YmdHis');
    }

    /**
     * Generates a merchant reference with exactly 15 characters.
     *
     * The prefix may contain letters, numbers and hyphens and must
     * contain no more than six characters.
     *
     * @throws InvalidRequestException
     */
    final public static function generateReference(string $prefix = 'R'): string
    {
        $prefix = strtoupper(trim($prefix));

        if ($prefix === '') {
            throw new InvalidRequestException(
                'O prefixo da referência não pode estar vazio.'
            );
        }

        if (strlen($prefix) > 6) {
            throw new InvalidRequestException(
                'O prefixo da referência deve ter no máximo 6 caracteres.'
            );
        }

        if (!preg_match('/^[A-Z0-9-]+$/', $prefix)) {
            throw new InvalidRequestException(
                'O prefixo da referência aceita apenas letras, números e hífen.'
            );
        }

        $remainingLength = 15 - strlen($prefix);
        $randomBytes = random_bytes((int) ceil($remainingLength / 2));
        $randomPart = strtoupper(bin2hex($randomBytes));

        return $prefix . substr($randomPart, 0, $remainingLength);
    }

    /**
     * Convert an ISO currency name or numeric code to its SISP code.
     *
     * @throws InvalidRequestException
     */
    protected function currencyToCode(string|int $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        if (preg_match('/^\d{3}$/', $currency)) {
            return $currency;
        }

        return match ($currency) {
            'CVE' => '132',
            'USD' => '840',
            'EUR' => '978',
            'BRL' => '986',
            'GBP' => '826',
            'JPY' => '392',

            default => throw new InvalidRequestException(
                "Moeda inválida: {$currency}."
            ),
        };
    }

    /**
     * Normalize a transaction amount without using floating-point values.
     *
     * @throws InvalidRequestException
     */
    protected function normalizeAmount(int|string $amount): string
    {
        $amount = trim((string) $amount);

        if (!preg_match('/^\d+$/', $amount)) {
            throw new InvalidRequestException(
                'O valor deve ser um inteiro positivo, sem casas decimais.'
            );
        }

        $amount = ltrim($amount, '0');
        $amount = $amount === '' ? '0' : $amount;

        if ($amount === '0') {
            throw new InvalidRequestException(
                'O valor da transação deve ser maior que zero.'
            );
        }

        if (strlen($amount) > 13) {
            throw new InvalidRequestException(
                'O valor da transação deve ter no máximo 13 dígitos.'
            );
        }

        return $amount;
    }

    /**
     * Convert an amount to the representation used in SISP fingerprints.
     *
     * SISP fingerprints represent the amount using three decimal positions.
     *
     * @throws InvalidRequestException
     */
    protected function fingerprintAmount(int|string $amount): string
    {
        return $this->normalizeAmount($amount) . '000';
    }

    /**
     * Convert a response amount to its fingerprint representation.
     *
     * @throws InvalidResponseException
     */
    protected function responseFingerprintAmount(mixed $amount): string
    {
        if (!is_int($amount) && !is_float($amount) && !is_string($amount)) {
            throw new InvalidResponseException(
                'O valor retornado pela SISP é inválido.'
            );
        }

        $amount = trim((string) $amount);

        if (!preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $amount, $matches)) {
            throw new InvalidResponseException(
                'O valor retornado pela SISP é inválido.'
            );
        }

        $integer = ltrim($matches[1], '0');
        $integer = $integer === '' ? '0' : $integer;

        $decimal = str_pad(
            $matches[2] ?? '',
            3,
            '0',
            STR_PAD_RIGHT,
        );

        $normalized = ltrim($integer . $decimal, '0');

        return $normalized === '' ? '0' : $normalized;
    }

    /**
     * Encode the POS authorization code for fingerprint generation.
     */
    protected function encodedAuthorizationCode(): string
    {
        return base64_encode(
            hash('sha512', $this->posAuthCode, true)
        );
    }

    /**
     * Generate the 3DS purchase request from normalized billing data.
     *
     * @param array<string, mixed> $billing
     *
     * @throws InvalidRequestException
     */
    protected function generatePurchaseRequest(array $billing): string
    {
        $required = [
            'email',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
        ];

        $missing = [];

        foreach ($required as $field) {
            if (!isset($billing[$field]) || trim((string) $billing[$field]) === '') {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new InvalidRequestException(
                'Campos obrigatórios ausentes no billing: '
                    . implode(', ', $missing)
                    . '.'
            );
        }

        try {
            $json = json_encode(
                $billing,
                JSON_THROW_ON_ERROR |
                    JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE,
            );
        } catch (JsonException $exception) {
            throw new InvalidRequestException(
                'Não foi possível gerar os dados 3DS do billing.',
                previous: $exception,
            );
        }

        return base64_encode($json);
    }

    /**
     * Validate common transaction parameters.
     *
     * Returns the first validation error or null when valid.
     *
     * @param array<string, mixed> $params
     */
    protected function validateParams(array $params): ?string
    {
        $transactionCode = (string) ($params['transactionCode'] ?? '');

        if (!in_array($transactionCode, ['1', '2', '3', '4'], true)) {
            return 'TransactionCode inválido. Valores permitidos: 1, 2, 3 e 4.';
        }

        $merchantRef = trim((string) ($params['merchantRef'] ?? ''));

        if ($merchantRef === '') {
            return 'MerchantRef é obrigatório.';
        }

        if (strlen($merchantRef) > 15) {
            return 'MerchantRef deve ter no máximo 15 caracteres.';
        }

        $merchantSession = trim(
            (string) ($params['merchantSession'] ?? '')
        );

        if ($merchantSession === '') {
            return 'MerchantSession é obrigatório.';
        }

        if (strlen($merchantSession) > 15) {
            return 'MerchantSession deve ter no máximo 15 caracteres.';
        }

        $amount = (string) ($params['amount'] ?? '');

        if (!preg_match('/^\d{1,13}$/', $amount) || $amount === '0') {
            return 'Amount deve ser um inteiro positivo de até 13 dígitos.';
        }

        $currency = (string) ($params['currency'] ?? '');

        if (!preg_match('/^\d{3}$/', $currency)) {
            return 'Currency deve ser um código ISO 4217 numérico de 3 dígitos.';
        }

        if (
            $transactionCode === self::TRANSACTION_TYPE_REFUND &&
            $currency !== self::CURRENCY_CVE
        ) {
            return 'Currency para reembolso deve ser 132 (CVE).';
        }

        $returnUrl = (string) ($params['urlMerchantResponse'] ?? '');

        if (filter_var($returnUrl, FILTER_VALIDATE_URL) === false) {
            return 'UrlMerchantResponse deve ser uma URL válida.';
        }

        $lang = strtolower(
            trim((string) ($params['languageMessages'] ?? ''))
        );

        if (!in_array($lang, ['pt', 'en', 'fr'], true)) {
            return "LanguageMessages deve ser 'pt', 'en' ou 'fr'.";
        }

        if (in_array($transactionCode, [
            self::TRANSACTION_TYPE_SERVICE,
            self::TRANSACTION_TYPE_RECHARGE,
        ], true)) {
            $entity = (string) ($params['entityCode'] ?? '');
            $reference = (string) ($params['referenceNumber'] ?? '');

            if (!preg_match('/^\d+$/', $entity) || $entity === '0') {
                return 'EntityCode deve ser um número inteiro positivo.';
            }

            if (!preg_match('/^\d{1,9}$/', $reference)) {
                return 'ReferenceNumber deve conter entre 1 e 9 dígitos.';
            }
        }

        if ($transactionCode === self::TRANSACTION_TYPE_REFUND) {
            $clearingPeriod = (string) (
                $params['clearingPeriod'] ?? ''
            );

            $transactionId = (string) (
                $params['transactionID'] ?? ''
            );

            if (!preg_match('/^\d{1,4}$/', $clearingPeriod)) {
                return 'ClearingPeriod deve conter entre 1 e 4 dígitos.';
            }

            if (!preg_match('/^\w{1,8}$/', $transactionId)) {
                return 'TransactionID deve conter no máximo 8 caracteres alfanuméricos.';
            }
        }

        return null;
    }

    /**
     * Build the provider URL containing fingerprint query parameters.
     *
     * @param array<string, mixed> $request
     */
    protected function buildPostUrl(array $request): string
    {
        return $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $request['fingerprint'],
            'TimeStamp' => $request['timeStamp'],
            'FingerPrintVersion' => $request['fingerprintversion'],
        ]);
    }
}
