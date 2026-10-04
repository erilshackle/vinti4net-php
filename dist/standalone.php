<?php

declare(strict_types=1);

/**
 * Standalone Vinti4Net integration for PHP 8.1+.
 *
 * This file does not require Composer. Include it with require_once and use
 * the global Vinti4Net class. Responses are returned as arrays and receipt
 * rendering is intentionally not included.
 *
 * This is a community integration and not an official SISP SDK.
 * @file
 */


/**  Vinti4Exception */
class Vinti4Exception extends RuntimeException {}

/**
 * Standalone Vinti4Net integration for PHP 8.1+.
 */
final class Vinti4Net
{
    public const DEFAULT_BASE_URL = 'https://mc.vinti4net.cv/BizMPIOnUsSisp';

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND = '4';

    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';

    private const CURRENCY_CVE = '132';
    private const ENDPOINT_PATH = '/CardPayment';
    private const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    private string $posID;
    private string $posAuthCode;
    private ?string $endpoint;

    /** @var array<string, mixed> */
    private array $request = [];

    private bool $prepared = false;

    /**
     * Create a standalone Vinti4Net client.
     *
     * @throws Vinti4Exception
     */
    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null,
    ) {
        $posID = trim($posID);
        $posAuthCode = trim($posAuthCode);
        $endpoint = $endpoint !== null ? trim($endpoint) : null;

        if ($posID === '') {
            throw new Vinti4Exception('O POS ID não pode estar vazio.');
        }

        if ($posAuthCode === '') {
            throw new Vinti4Exception('O código de autenticação não pode estar vazio.');
        }

        if ($endpoint !== null && filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new Vinti4Exception('A URL base da SISP deve ser válida.');
        }

        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->endpoint = $endpoint;
    }

    /**
     * Generate a 15-character merchant reference.
     *
     * Format: R + ymdHis + two random alphanumeric characters.
     */
    public static function generateMerchantRef(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $suffix = $characters[random_int(0, 35)] . $characters[random_int(0, 35)];

        return 'R' . date('ymdHis') . $suffix;
    }

    /**
     * Configure the merchant reference and session.
     *
     * Both values must contain exactly 15 characters.
     */
    public function setMerchant(string $reference, ?string $session = null): self
    {
        $this->request['merchantRef'] = trim($reference);
        $this->request['merchantSession'] = $session !== null ? trim($session) : 'S' . date('YmdHis');
        return $this;
    }

    /**
     * Prepare a purchase.
     *
     * Pass an empty billing array to omit purchaseRequest.
     * Friendly billing names and the original SISP field names are accepted.
     *
     * @param array<string, mixed> $billing
     * @param 'CVE'|'132'|'EUR'|'978'|'USD'|'840'|'BRL'|'986'|'GBP'|'826'|'JPY'|'392'|string $currency ISO 4217 name or numeric code.
     * Other codes depend on gateway support; the documented SISP protocol uses CVE.
     * @see https://www.iso.org/iso-4217-currency-codes.html
     */
    public function preparePurchase(
        float|string $amount,
        array $billing = [],
        string $currency = 'CVE',
    ): self {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'currency' => $currency,
            'billing' => $billing,
        ]);

        return $this;
    }

    /** Prepare a service payment. */
    public function prepareServicePayment(
        float|string $amount,
        int $entity,
        string $number,
    ): self {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /** Prepare a recharge payment. */
    public function prepareRecharge(
        float|string $amount,
        int $entity,
        string $number,
    ): self {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /** Prepare a refund. */
    public function prepareRefund(
        float|string $amount,
        string $transactionID,
        string $clearingPeriod,
    ): self {
        $this->prepareRequest([
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ]);

        return $this;
    }

    /**
     * Generate an auto-submitting payment form.
     *
     * @throws Vinti4Exception
     */
    public function createPaymentForm(string $responseUrl, string $lang = 'pt'): string
    {
        if (!$this->prepared) {
            throw new Vinti4Exception('Nenhum pagamento preparado.');
        }

        $params = $this->request;
        $params['languageMessages'] = strtolower(trim($lang));
        $params['urlMerchantResponse'] = trim($responseUrl);

        $prepared = ($params['transactionCode'] ?? '') === self::TRANSACTION_TYPE_REFUND
            ? $this->prepareRefundRequest($params)
            : $this->preparePaymentRequest($params);

        $fields = $prepared['fields'];
        $postUrl = $prepared['postUrl'];
        $inputs = '';

        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $name = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">\n";
        }

        $action = htmlspecialchars($postUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $processing = $params['languageMessages'] === 'pt'
            ? 'Processando...'
            : 'Processing...';

        return <<<HTML
<!doctype html>
<html lang="{$params['languageMessages']}">
<head>
    <meta charset="UTF-8">
    <title>Pagamento Vinti4Net</title>
</head>
<body onload="document.forms[0].submit()">
    <form method="post" action="{$action}">
{$inputs}    </form>
    <p>{$processing}</p>
</body>
</html>
HTML;
    }

    /**
     * Process a callback and return a normalized array.
     *
     * @param array<string, mixed> $postData Raw POST data received from SISP.
     *
     * @return array{
     *     status: 'SUCCESS'|'ERROR'|'CANCELLED'|'INVALID_FINGERPRINT',
     *     message: string,
     *     success: bool,
     *     data: array<string, mixed>,
     *     dcc: array<string, mixed>,
     *     debug: array<string, string>,
     *     detail: string|null,
     *     operation: string|null
     * }
     *
     * @throws Vinti4Exception
     */
    public function processResponse(array $postData): array
    {
        if ($postData === []) {
            throw new Vinti4Exception('A resposta da SISP está vazia.');
        }

        $messageType = trim((string) ($postData['messageType'] ?? ''));
        $successType = in_array($messageType, self::SUCCESS_MESSAGE_TYPES, true);
        $transactionSuccessful = $successType;

        $transactionSuccessful = match ($messageType) {
            '8', 'P', 'M' => trim((string) ($postData['merchantResp'] ?? '')) === 'C',
            '10' => true,
            default => false,
        };

        $cancelled = $messageType === ''
            && filter_var($postData['UserCancelled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && isset($postData['merchantRef'], $postData['merchantSession']);
        $fingerprintValid = $cancelled;
        $calculatedFingerprint = null;

        if ($successType || $messageType === '6') {
            $calculatedFingerprint = $messageType === '6'
                ? $this->fingerprintErrorResponse($postData)
                : $this->fingerprintResponse($postData);

            $receivedFingerprint = trim((string) ($postData['resultFingerPrint'] ?? ''));
            $fingerprintValid = $receivedFingerprint !== ''
                && hash_equals($calculatedFingerprint, $receivedFingerprint);
        }

        if ($fingerprintValid === false) {
            $status = self::STATUS_INVALID_FINGERPRINT;
        } elseif ($cancelled) {
            $status = self::STATUS_CANCELLED;
        } elseif ($transactionSuccessful) {
            $status = self::STATUS_SUCCESS;
        } else {
            $status = self::STATUS_ERROR;
        }

        $operation = match ($messageType) {
            '10' => 'refund',
            '8' => 'purchase',
            'P' => 'service_payment',
            'M' => 'recharge',
            default => null,
        };

        if ($status === self::STATUS_CANCELLED) {
            $message = 'Utilizador cancelou a transação.';
        } elseif ($status === self::STATUS_SUCCESS) {
            $message = $operation === 'refund'
                ? 'Reembolso processado com sucesso.'
                : 'Transação válida.';
        } elseif ($status === self::STATUS_INVALID_FINGERPRINT) {
            $message = 'Fingerprint inválido (verificar segurança).';
        } else {
            $message = 'Transação falhou.';

            foreach (
                [
                    'merchantRespAdditionalErrorMessage',
                    'merchantRespErrorDetail',
                    'merchantRespErrorDescription',
                ] as $field
            ) {
                $providerMessage = trim((string) ($postData[$field] ?? ''));

                if ($providerMessage !== '') {
                    $message = $providerMessage;
                    break;
                }
            }
        }

        $dcc = $this->extractDcc($postData);
        $debug = $fingerprintValid === false
            ? [
                'received' => (string) ($postData['resultFingerPrint'] ?? ''),
                'calculated' => (string) $calculatedFingerprint,
            ]
            : [];

        $safeData = $postData;

        if (isset($safeData['merchantRespPan'])) {
            $pan = preg_replace('/\D+/', '', (string) $safeData['merchantRespPan']) ?? '';
            $safeData['merchantRespPan'] = $pan !== '' && $pan !== '0'
                ? '•••• ' . substr($pan, -4)
                : null;
        }

        return [
            'status' => $status,
            'message' => $message,
            'success' => $status === self::STATUS_SUCCESS,
            'data' => $safeData,
            'dcc' => $dcc,
            'debug' => $debug,
            'detail' => isset($postData['merchantRespErrorDetail'])
                ? (string) $postData['merchantRespErrorDetail']
                : null,
            'operation' => $operation,
        ];
    }

    /** Return the currently prepared transaction data. */
    public function getRequest(): array
    {
        return $this->request;
    }

    /** @param array<string, mixed> $transaction */
    private function prepareRequest(array $transaction): void
    {
        $persistent = array_intersect_key(
            $this->request,
            array_flip([
                'merchantRef',
                'merchantSession',
                'languageMessages',
                'timeStamp',
            ]),
        );

        $this->request = array_merge($transaction, $persistent);
        $this->prepared = true;
    }

    /** @return array{fields: array<string, mixed>, postUrl: string} */
    private function preparePaymentRequest(array $params): array
    {
        $transactionCode = (string) ($params['transactionCode'] ?? '');

        if ($transactionCode === '') {
            throw new Vinti4Exception('transactionCode é obrigatório.');
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? self::generateMerchantRef(),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => $this->normalizeRequestAmount($params['amount'] ?? ''),
            'currency' => $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE),
            'transactionCode' => $transactionCode,
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
            'timeStamp' => $params['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
        ];

        if ($transactionCode === self::TRANSACTION_TYPE_PURCHASE && !empty($params['billing'])) {
            $billing = $this->normalizeBilling((array) $params['billing']);
            $request['purchaseRequest'] = $this->generatePurchaseRequest($billing);
        }

        if ($error = $this->validateRequest($request)) {
            throw new Vinti4Exception($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest($request);

        return [
            'postUrl' => $this->buildPostUrl($request),
            'fields' => $request,
        ];
    }

    /** @return array{fields: array<string, mixed>, postUrl: string} */
    private function prepareRefundRequest(array $params): array
    {
        foreach (['amount', 'urlMerchantResponse', 'clearingPeriod', 'transactionID'] as $field) {
            if (empty($params[$field])) {
                throw new Vinti4Exception("Campo obrigatório faltando: {$field}");
            }
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? self::generateMerchantRef(),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => $this->normalizeRequestAmount($params['amount']),
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => '1',
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'urlMerchantResponse' => $params['urlMerchantResponse'],
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'timeStamp' => $params['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'entityCode' => '',
            'referenceNumber' => '',
            'reversal' => 'R',
            'clearingPeriod' => $params['clearingPeriod'],
            'transactionID' => $params['transactionID'],
        ];

        if ($error = $this->validateRequest($request)) {
            throw new Vinti4Exception($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest($request);

        return [
            'postUrl' => $this->buildPostUrl($request),
            'fields' => $request,
        ];
    }

    /** @param array<string, mixed> $request */
    private function buildPostUrl(array $request): string
    {
        $endpoint = $this->endpoint
            ?? rtrim(self::DEFAULT_BASE_URL, '/') . self::ENDPOINT_PATH;

        return $endpoint . '?' . http_build_query([
            'FingerPrint' => $request['fingerprint'],
            'TimeStamp' => $request['timeStamp'],
            'FingerPrintVersion' => $request['fingerprintversion'],
        ]);
    }

    /** @param array<string, mixed> $data */
    private function fingerprintRequest(array $data): string
    {
        $entity = !empty($data['entityCode']) ? (int) $data['entityCode'] : '';
        $reference = !empty($data['referenceNumber']) ? (int) $data['referenceNumber'] : '';

        $toHash = $this->encodedAuthCode()
            . ($data['timeStamp'] ?? '')
            . $this->amountToLong($data['amount'] ?? null)
            . ($data['merchantRef'] ?? '')
            . ($data['merchantSession'] ?? '')
            . ($data['posID'] ?? '')
            . ($data['currency'] ?? '')
            . ($data['transactionCode'] ?? '')
            . $entity
            . $reference;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /** @param array<string, mixed> $data */
    private function fingerprintResponse(array $data): string
    {
        $message = $this->encodedAuthCode()
            . trim((string) ($data['messageType'] ?? ''))
            . $this->fingerprintNumber($data['merchantRespCP'] ?? '', false)
            . $this->fingerprintNumber($data['merchantRespTid'] ?? '', false)
            . trim((string) ($data['merchantRespMerchantRef'] ?? ''))
            . trim((string) ($data['merchantRespMerchantSession'] ?? ''))
            . $this->amountToLong($data['merchantRespPurchaseAmount'] ?? null)
            . trim((string) ($data['merchantRespMessageID'] ?? ''))
            . trim((string) ($data['merchantRespPan'] ?? ''))
            . trim((string) ($data['merchantResp'] ?? ''))
            . trim((string) ($data['merchantRespTimeStamp'] ?? ''))
            . $this->fingerprintNumber($data['merchantRespEntityCode'] ?? '')
            . $this->fingerprintNumber($data['merchantRespReferenceNumber'] ?? '')
            . trim((string) ($data['merchantRespClientReceipt'] ?? ''))
            . trim((string) ($data['merchantRespAdditionalErrorMessage'] ?? ''))
            . (preg_replace('/\s+/u', '', (string) ($data['merchantRespReloadCode'] ?? '')) ?? '');

        return base64_encode(hash('sha512', $message, true));
    }

    /** Normalize numeric hash fields without integer overflow. */
    private function fingerprintNumber(mixed $value, bool $numeric = true): string
    {
        $value = preg_replace('/\s+/u', '', (string) ($value ?? '')) ?? '';
        if ($value === '') {
            return '';
        }
        if ($numeric && !ctype_digit($value)) {
            throw new Vinti4Exception('Invalid numeric callback field.');
        }
        return ltrim($value, '0') ?: '0';
    }


    /**
     * Calculate the error response fingerprint (messageType 6).
     *
     * @param array<string, mixed> $data Callback fields in protocol order.
     */
    private function fingerprintErrorResponse(array $data): string
    {
        $message = $this->encodedAuthCode();
        foreach ([
            'messageType', 'merchantRespMessageID', 'merchantRespErrorCode',
            'merchantRespErrorDetail', 'merchantRespErrorDescription',
            'merchantRespMerchantRef', 'merchantRespMerchantSession',
            'merchantRespAdditionalErrorMessage', 'merchantRespTimeStamp',
        ] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $message .= $field === 'merchantRespErrorCode'
                ? preg_replace('/\s+/u', '', $value)
                : $value;
        }

        return base64_encode(hash('sha512', $message, true));
    }

    private function encodedAuthCode(): string
    {
        return base64_encode(hash('sha512', $this->posAuthCode, true));
    }

    private function normalizeRequestAmount(int|float|string $amount): string
    {
        $value = trim((string) $amount);

        if (!preg_match('/^[1-9]\d{0,12}$/', $value)) {
            throw new Vinti4Exception(
                'Amount deve ser um inteiro positivo com no máximo 13 dígitos.'
            );
        }

        return $value;
    }

    private function amountToLong(int|float|string|null $amount): string
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

    private function currencyToCode(string|int $currency): string
    {
        $currency = strtoupper(trim((string) $currency));

        return match ($currency) {
            'CVE' => '132',
            'USD' => '840',
            'EUR' => '978',
            'BRL' => '986',
            'GBP' => '826',
            'JPY' => '392',
            default => preg_match('/^\d{3}$/', $currency)
                ? $currency
                : throw new Vinti4Exception("Moeda inválida: {$currency}."),
        };
    }

    /** @param array<string, mixed> $params */
    private function validateRequest(array $params): ?string
    {
        $transactionCode = (string) ($params['transactionCode'] ?? '');

        if (!in_array($transactionCode, ['1', '2', '3', '4'], true)) {
            return 'TransactionCode não suportado. Valores válidos: 1,2,3,4.';
        }

        if (strlen(trim((string) ($params['merchantRef'] ?? ''))) !== 15) {
            return 'MerchantRef é obrigatório e deve ter exatamente 15 caracteres.';
        }

        if (strlen(trim((string) ($params['merchantSession'] ?? ''))) !== 15) {
            return 'MerchantSession é obrigatório e deve ter exatamente 15 caracteres.';
        }

        if (in_array($transactionCode, ['2', '3'], true)) {
            if (!preg_match('/^\d{1,5}$/', (string) ($params['entityCode'] ?? ''))
                || (int) $params['entityCode'] < 1) {
                return 'EntityCode é obrigatório e deve ser numérico.';
            }

            if (!preg_match('/^\d{1,9}$/', (string) ($params['referenceNumber'] ?? ''))) {
                return 'ReferenceNumber é obrigatório e deve ter até 9 dígitos.';
            }
        }

        if (!preg_match('/^[1-9]\d{0,12}$/', (string) ($params['amount'] ?? ''))) {
            return 'Amount deve ser um inteiro positivo com até 13 dígitos.';
        }

        if (!preg_match('/^\d{3}$/', (string) ($params['currency'] ?? ''))) {
            return 'Currency deve ser um código numérico ISO 4217 de 3 dígitos.';
        }

        if (
            $transactionCode === self::TRANSACTION_TYPE_REFUND
            && $params['currency'] !== self::CURRENCY_CVE
        ) {
            return "Currency para estorno deve ser '132' (CVE).";
        }

        if (filter_var($params['urlMerchantResponse'] ?? null, FILTER_VALIDATE_URL) === false) {
            return 'UrlMerchantResponse deve ser uma URL válida.';
        }

        if (!in_array(strtolower((string) ($params['languageMessages'] ?? '')), ['pt', 'en', 'fr'], true)) {
            return "LanguageMessages deve ser 'pt', 'en' ou 'fr'.";
        }

        if ($transactionCode === self::TRANSACTION_TYPE_REFUND) {
            if (!preg_match('/^\d{1,4}$/', (string) ($params['clearingPeriod'] ?? ''))) {
                return 'ClearingPeriod deve ter até 4 dígitos numéricos.';
            }

            if (!preg_match('/^[A-Za-z0-9]{1,8}$/', (string) ($params['transactionID'] ?? ''))) {
                return 'TransactionID deve ter até 8 caracteres alfanuméricos.';
            }
        }

        return null;
    }

    /**
     * Normalize friendly and original SISP billing keys.
     *
     * @param array<string, mixed> $billing
     * @return array<string, mixed>
     */
    private function normalizeBilling(array $billing): array
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
            'phone' => 'mobilePhone',
            'mobilePhone' => 'mobilePhone',
            'workPhone' => 'workPhone',
            'accountId' => 'acctID',
            'acctID' => 'acctID',
            'accountInfo' => 'acctInfo',
            'acctInfo' => 'acctInfo',
            'addressMatchesShipping' => 'addrMatch',
            'addrMatch' => 'addrMatch',
        ];

        $normalized = ['billAddrCountry' => '132'];

        foreach ($billing as $key => $value) {
            $field = $map[$key] ?? null;

            if ($field === null) {
                continue;
            }

            if ($field === 'mobilePhone' || $field === 'workPhone') {
                if (is_string($value) || is_int($value)) {
                    $value = ['cc' => '238', 'subscriber' => (string) $value];
                }

                if (is_array($value)) {
                    $cc = preg_replace('/\D+/', '', (string) ($value['cc'] ?? '238')) ?? '';
                    $subscriber = preg_replace('/\D+/', '', (string) ($value['subscriber'] ?? '')) ?? '';
                    $value = $subscriber !== ''
                        ? [
                            'cc' => $cc !== '' ? $cc : '238',
                            'subscriber' => $subscriber,
                        ]
                        : null;
                } else {
                    $value = null;
                }
            }

            if ($field === 'addrMatch') {
                $value = is_bool($value) ? ($value ? 'Y' : 'N') : strtoupper(trim((string) $value));
                if (!in_array($value, ['Y', 'N'], true)) {
                    throw new Vinti4Exception('addrMatch deve ser Y, N ou booleano.');
                }
            }

            $normalized[$field] = is_string($value) ? trim($value) : $value;
        }

        if (array_key_exists('suspicious', $billing)) {
            $accountInfo = is_array($normalized['acctInfo'] ?? null)
                ? $normalized['acctInfo']
                : [];
            $accountInfo['suspiciousAccActivity'] = $billing['suspicious'] ? '02' : '01';
            $normalized['acctInfo'] = $accountInfo;
        }

        if (isset($normalized['acctInfo']) && is_array($normalized['acctInfo'])) {
            $normalized['acctInfo'] = array_filter(
                array_merge([
                    'chAccChange' => '',
                    'chAccDate' => '',
                    'chAccPwChange' => '',
                    'suspiciousAccActivity' => '01',
                ], $normalized['acctInfo']),
                static fn(mixed $value): bool => $value !== null && $value !== '',
            );
        }

        if (!empty($normalized['billAddrLine1']) && empty($normalized['billAddrLine2'])) {
            $normalized['billAddrLine2'] = $normalized['billAddrLine1'];
        }
        if (($normalized['addrMatch'] ?? null) === 'Y') {
            foreach (['Country', 'City', 'Line1', 'PostCode', 'State'] as $suffix) {
                $normalized['shipAddr' . $suffix] = $normalized['billAddr' . $suffix] ?? '';
            }
        }

        return array_filter(
            $normalized,
            static fn(mixed $value): bool =>
            $value !== null && $value !== '' && $value !== [],
        );
    }

    /** @param array<string, mixed> $billing */
    private function generatePurchaseRequest(array $billing): string
    {
        if (!isset($billing['billAddrPostCode']) || trim((string) $billing['billAddrPostCode']) === '') {
            $billing['billAddrPostCode'] = '0000';
        }
        if (($billing['addrMatch'] ?? null) === 'Y') {
            $billing['shipAddrPostCode'] = $billing['billAddrPostCode'];
        }

        $this->validateBilling($billing);

        try {
            $json = json_encode(
                $billing,
                JSON_UNESCAPED_SLASHES
                    | JSON_UNESCAPED_UNICODE
                    | JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $exception) {
            throw new Vinti4Exception(
                'Erro ao gerar JSON para billing (purchaseRequest).',
                0,
                $exception,
            );
        }

        return base64_encode($json);
    }

    /** @param array<string, mixed> $billing */
    private function validateBilling(array $billing): void
    {
        $required = ['email', 'billAddrCountry', 'billAddrCity', 'billAddrLine1'];
        $missing = array_filter(
            $required,
            static fn(string $field): bool =>
                !isset($billing[$field]) || trim((string) $billing[$field]) === '',
        );

        if ($missing !== []) {
            throw new Vinti4Exception(
                'Campos obrigatórios ausentes em billing: ' . implode(', ', $missing) . '.'
            );
        }

        if (filter_var($billing['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new Vinti4Exception('Email de billing inválido.');
        }

        if (isset($billing['acctID']) && preg_match_all('/./us', (string) $billing['acctID']) > 64) {
            throw new Vinti4Exception('acctID deve ter no máximo 64 caracteres.');
        }
    }

    /** @param array<string, mixed> $data */
    private function extractDcc(array $data): array
    {
        $rawDcc = trim((string) ($data['merchantRespDCCData'] ?? ''));

        if ($rawDcc === '') {
            return ['enabled' => false];
        }

        $dcc = json_decode($rawDcc, true);

        if (!is_array($dcc)) {
            return [
                'enabled' => false,
                'error' => 'DCC inválido ou mal formatado.',
            ];
        }

        return [
            'enabled' => ($dcc['dcc'] ?? 'N') === 'Y',
            'amount' => $dcc['dccAmount'] ?? null,
            'currency' => $dcc['dccCurrency'] ?? null,
            'markup' => $dcc['dccMarkup'] ?? null,
            'rate' => $dcc['dccRate'] ?? null,
        ];
    }
}
