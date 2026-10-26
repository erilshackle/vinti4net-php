<?php

declare(strict_types=1);

/**
 * SISP Vinti4Net protocol integration for PHP 8.1+.
 *
 * This file has no Composer dependency. It accepts protocol field names and
 * values as supplied by the caller; it does not translate billing aliases,
 * country codes or currency names.
 *
 * @author Eril TS Carvalho <erilandocarvalho@gmail.com>
 * @see https://github.com/erilshackle/vinti4net-php
 * @see https://erilshackle.github.io/vinti4net-php/
 */

class SispVinti4NetException extends RuntimeException {}

/**
 * Client for preparing and validating SISP Vinti4Net protocol requests.
 *
 * Values and field names are sent in their SISP form without friendly aliases
 * or country/currency conversion.
 *
 * @author Eril TS Carvalho <erilandocarvalho@gmail.com>
 * @see https://github.com/erilshackle/vinti4net-php
 * @see https://erilshackle.github.io/vinti4net-php/
 */
final class SispVinti4Net
{
    public const DEFAULT_BASE_URL = 'https://mc.vinti4net.cv/BizMPIOnUsSisp';
    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND = '4';
    public const TRANSACTION_TYPE_TOKEN_ENROLLMENT = '5';
    public const TRANSACTION_TYPE_TOKEN_PURCHASE = '6';
    public const TRANSACTION_TYPE_TOKEN_CANCELLATION = '7';

    private string $posID;
    private string $posAuthCode;
    private ?string $endpoint;

    /** @var array<string, mixed> */
    private array $request = [];

    /** @var array<string, mixed>|null */
    private ?array $preparedFields = null;

    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        if (trim($posID) === '' || trim($posAuthCode) === '') {
            throw new SispVinti4NetException('POS ID e código de autenticação são obrigatórios.');
        }
        if ($endpoint !== null && filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new SispVinti4NetException('A URL da SISP deve ser válida.');
        }

        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->endpoint = $endpoint;
    }

    public static function generateMerchantRef(): string
    {
        $characters = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        return 'R' . date('ymdHis')
            . $characters[random_int(0, 35)]
            . $characters[random_int(0, 35)];
    }

    /** @param array<string, mixed> $params */
    public function setRequestParams(array $params): self
    {
        foreach ($params as $key => $value) {
            if (!in_array($key, ['merchantRef', 'merchantSession', 'languageMessages', 'timeStamp'], true)) {
                throw new SispVinti4NetException("Parâmetro de pedido não permitido: {$key}.");
            }
            $this->request[$key] = $value;
        }
        return $this;
    }

    public function setMerchant(string $reference, ?string $session = null): self
    {
        $this->request['merchantRef'] = $reference;
        $this->request['merchantSession'] = $session ?? ('S' . date('YmdHis'));
        return $this;
    }

    /**
     * Prepare a transaction from raw SISP fields.
     *
     * For a purchase, pass billing under its original SISP names (for example
     * billAddrCountry), or provide an already Base64-encoded purchaseRequest.
     *
     * @param array<string, mixed> $params
     */
    public function preparePayment(array $params): self
    {
        $this->prepareRequest($params);
        return $this;
    }

    /** Prepare a purchase. Currency must be a three-digit ISO 4217 numeric code. */
    public function preparePurchase(float|string $amount, array $billing = [], string $currency = '132'): self
    {
        return $this->preparePayment([
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'currency' => $currency,
            'billing' => $billing,
        ]);
    }

    public function prepareServicePayment(float|string $amount, string $entityCode, string $referenceNumber): self
    {
        return $this->preparePayment([
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
        ]);
    }

    public function prepareRecharge(float|string $amount, string $entityCode, string $referenceNumber): self
    {
        return $this->preparePayment([
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entityCode,
            'referenceNumber' => $referenceNumber,
        ]);
    }

    public function prepareRefund(float|string $amount, string $transactionID, string $clearingPeriod): self
    {
        return $this->preparePayment([
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ]);
    }

    public function prepareTokenRequest(float|string $minimumAmount): self
    {
        return $this->prepareToken('5', '', $minimumAmount);
    }

    public function prepareTokenPurchase(string $token, float|string $amount): self
    {
        return $this->prepareToken('6', $token, $amount);
    }

    public function prepareTokenServicePayment(
        string $token,
        float|string $amount,
        string $entityCode,
        string $referenceNumber,
    ): self {
        return $this->prepareToken('2', $token, $amount, $entityCode, $referenceNumber);
    }

    public function prepareTokenRecharge(
        string $token,
        float|string $amount,
        string $entityCode,
        string $referenceNumber,
    ): self {
        return $this->prepareToken('3', $token, $amount, $entityCode, $referenceNumber);
    }

    public function prepareTokenCancellation(string $token): self
    {
        return $this->prepareToken('7', $token, '0');
    }

    /** @return array<string, mixed> */
    public function getRequest(): array
    {
        return $this->request;
    }

    /** @return array<string, mixed>|null */
    public function getPreparedFields(): ?array
    {
        return $this->preparedFields;
    }

    public function createPaymentForm(string $responseUrl, string $lang = 'pt'): string
    {
        if (!isset($this->request['transactionCode'])) {
            throw new SispVinti4NetException('Nenhuma operação foi preparada.');
        }
        if (!in_array($lang, ['pt', 'en', 'fr'], true)) {
            throw new SispVinti4NetException('LanguageMessages deve ser pt, en ou fr.');
        }

        $params = $this->request;
        $params['languageMessages'] = $lang;
        $params['urlMerchantResponse'] = $responseUrl;
        $fields = $this->buildRequest($params);
        $this->preparedFields = $fields;
        $url = htmlspecialchars($this->buildPostUrl($fields), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $inputs = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $name = htmlspecialchars((string) $key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $inputs .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">\n";
        }
        $processing = $lang === 'pt' ? 'Processando...' : 'Processing...';

        return <<<HTML
<!doctype html>
<html lang="{$lang}">
<head><meta charset="UTF-8"><title>Pagamento Vinti4Net</title></head>
<body onload="document.forms[0].submit()">
<form method="post" action="{$url}">
{$inputs}</form>
<p>{$processing}</p>
</body>
</html>
HTML;
    }

    /**
     * Validate an ordinary payment/refund callback, returning the original fields.
     *
     * @param array<string, mixed> $postData
     * @return array{success: bool, fingerprint_valid: bool, calculated_fingerprint: ?string, message_type: string, cancelled: bool, data: array<string, mixed>}
     */
    public function processResponse(array $postData): array
    {
        if ($postData === []) {
            throw new SispVinti4NetException('A resposta da SISP está vazia.');
        }

        $type = trim((string) ($postData['messageType'] ?? ''));
        $successType = in_array($type, ['8', '10', 'P', 'M'], true);
        $cancelled = $type === ''
            && filter_var($postData['UserCancelled'] ?? false, FILTER_VALIDATE_BOOLEAN)
            && isset($postData['merchantRef'], $postData['merchantSession']);
        $fingerprint = null;
        $valid = $cancelled;
        if ($successType || $type === '6') {
            $fingerprint = $type === '6'
                ? $this->fingerprintErrorResponse($postData)
                : $this->fingerprintPaymentResponse($postData);
            $received = trim((string) ($postData['resultFingerPrint'] ?? ''));
            $valid = $received !== '' && hash_equals($fingerprint, $received);
        }

        $success = $valid && match ($type) {
            '8', 'P', 'M' => ($postData['merchantResp'] ?? '') === 'C',
            '10' => true,
            default => false,
        };

        return [
            'success' => $success,
            'fingerprint_valid' => $valid,
            'calculated_fingerprint' => $fingerprint,
            'message_type' => $type,
            'cancelled' => $cancelled,
            'data' => $postData,
        ];
    }

    /**
     * Validate a token callback. Always pass expectedCode from the saved request.
     *
     * @param array<string, mixed> $postData
     * @return array{success: bool, fingerprint_valid: bool, calculated_fingerprint: ?string, message_type: string, cancelled: bool, data: array<string, mixed>}
     */
    public function processTokenResponse(array $postData, ?string $expectedCode = null): array
    {
        if ($postData === []) {
            throw new SispVinti4NetException('A resposta da SISP está vazia.');
        }
        if ($expectedCode !== null && !in_array($expectedCode, ['2', '3', '5', '6', '7'], true)) {
            throw new SispVinti4NetException('Código de operação de token inválido.');
        }

        $type = (string) ($postData['messageType'] ?? '');
        if ($type === '6' || $type === '') {
            if (isset($postData['userCancelled']) && !isset($postData['UserCancelled'])) {
                $postData['UserCancelled'] = $postData['userCancelled'];
            }
            return $this->processResponse($postData);
        }

        $fingerprint = null;
        $valid = false;
        $matches = false;
        try {
            $this->validateTokenResponse($postData, $expectedCode);
            $fingerprint = $this->fingerprintTokenResponse($postData);
            $received = $postData['resultFingerPrint'] ?? null;
            $valid = is_string($received) && hash_equals($fingerprint, $received);
            $expectedType = match ($expectedCode) {
                '5' => 'A',
                '7' => 'C',
                '2', '3', '6' => 'B',
                default => null,
            };
            $matches = $expectedType === null || $type === $expectedType;
            if ($expectedCode === '7' || $type === 'C') {
                $matches = $matches && $this->amountToLong($postData['merchantRespPurchaseAmount'] ?? null) === '0';
            }
        } catch (SispVinti4NetException) {
            $valid = false;
        }

        return [
            'success' => $valid && $matches && ($postData['merchantResp'] ?? '') === '0',
            'fingerprint_valid' => $valid,
            'calculated_fingerprint' => $fingerprint,
            'message_type' => $type,
            'cancelled' => false,
            'data' => $postData,
        ];
    }

    private function prepareToken(
        string $code,
        string $token,
        float|string $amount,
        ?string $entityCode = null,
        ?string $referenceNumber = null,
    ): self {
        $transaction = [
            'transactionCode' => $code,
            'amount' => $amount,
            'currency' => '132',
            'token' => $token,
        ];
        if ($entityCode !== null) {
            $transaction['entityCode'] = $entityCode;
        }
        if ($referenceNumber !== null) {
            $transaction['referenceNumber'] = $referenceNumber;
        }
        return $this->preparePayment($transaction);
    }

    /** @param array<string, mixed> $transaction */
    private function prepareRequest(array $transaction): void
    {
        $persistent = array_intersect_key(
            $this->request,
            array_flip(['merchantRef', 'merchantSession', 'languageMessages', 'timeStamp']),
        );
        $this->request = array_merge($transaction, $persistent);
        $this->preparedFields = null;
    }

    /** @param array<string, mixed> $params
     *  @return array<string, mixed>
     */
    private function buildRequest(array $params): array
    {
        $code = (string) ($params['transactionCode'] ?? '');
        if (!in_array($code, ['1', '2', '3', '4', '5', '6', '7'], true)) {
            throw new SispVinti4NetException('TransactionCode deve ser 1, 2, 3, 4, 5, 6 ou 7.');
        }
        $tokenOperation = isset($params['token'])
            || in_array($code, ['5', '6', '7'], true);
        if ($tokenOperation && !in_array($code, ['2', '3', '5', '6', '7'], true)) {
            throw new SispVinti4NetException('TransactionCode incompatível com token.');
        }

        $amount = $this->normalizeAmount($params['amount'] ?? null, $code === '7');
        $currency = (string) ($params['currency'] ?? '132');
        $request = [
            'posID' => $this->posID,
            'merchantRef' => (string) ($params['merchantRef'] ?? self::generateMerchantRef()),
            'merchantSession' => (string) ($params['merchantSession'] ?? ('S' . date('YmdHis'))),
            'amount' => $amount,
            'currency' => $currency,
            'transactionCode' => $code,
            'languageMessages' => (string) ($params['languageMessages'] ?? 'pt'),
            'entityCode' => (string) ($params['entityCode'] ?? ''),
            'referenceNumber' => (string) ($params['referenceNumber'] ?? ''),
            'timeStamp' => (string) ($params['timeStamp'] ?? date('Y-m-d H:i:s')),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => (string) ($params['urlMerchantResponse'] ?? ''),
        ];

        if ($tokenOperation) {
            $request['token'] = (string) ($params['token'] ?? '');
        }
        if ($code === '4') {
            $request['reversal'] = 'R';
            $request['transactionID'] = (string) ($params['transactionID'] ?? '');
            $request['clearingPeriod'] = (string) ($params['clearingPeriod'] ?? '');
        }
        if ($code === '1') {
            if (isset($params['purchaseRequest'])) {
                if (!is_string($params['purchaseRequest'])) {
                    throw new SispVinti4NetException('purchaseRequest deve ser uma string Base64.');
                }
                $request['purchaseRequest'] = $params['purchaseRequest'];
            } elseif (isset($params['billing']) && $params['billing'] !== []) {
                $request['purchaseRequest'] = $this->encodeBilling($params['billing']);
            }
        }

        $this->validateRequest($request, $tokenOperation);
        $request['fingerprint'] = $tokenOperation
            ? $this->fingerprintTokenRequest($request)
            : $this->fingerprintPaymentRequest($request);
        return $request;
    }

    /** @param mixed $amount */
    private function normalizeAmount(mixed $amount, bool $zeroAllowed = false): string
    {
        $value = trim((string) $amount);
        $pattern = $zeroAllowed ? '/^(?:0|[1-9]\d{0,12})$/' : '/^[1-9]\d{0,12}$/';
        if (!preg_match($pattern, $value)) {
            throw new SispVinti4NetException('Amount deve ser inteiro em unidades mínimas, com até 13 dígitos.');
        }
        return $value;
    }

    private function encodeBilling(mixed $billing): string
    {
        if (!is_array($billing)) {
            throw new SispVinti4NetException('billing deve ser um array de campos SISP.');
        }
        foreach (['email', 'billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode'] as $key) {
            if (!isset($billing[$key]) || trim((string) $billing[$key]) === '') {
                throw new SispVinti4NetException("Campo obrigatório ausente em billing: {$key}.");
            }
        }
        if (!preg_match('/^\d{3}$/', (string) $billing['billAddrCountry'])) {
            throw new SispVinti4NetException('billAddrCountry deve ser um código ISO 3166-1 numérico de três dígitos.');
        }
        if (filter_var($billing['email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new SispVinti4NetException('Email de billing inválido.');
        }
        try {
            $json = json_encode($billing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new SispVinti4NetException('Não foi possível codificar billing em JSON.', 0, $exception);
        }
        return base64_encode($json);
    }

    /** @param array<string, mixed> $request */
    private function validateRequest(array $request, bool $tokenOperation): void
    {
        foreach (['merchantRef', 'merchantSession'] as $field) {
            if (trim($request[$field]) === '') {
                throw new SispVinti4NetException("{$field} é obrigatório.");
            }
        }
        if (!preg_match('/^\d{3}$/', $request['currency'])) {
            throw new SispVinti4NetException('Currency deve ser o código numérico ISO 4217 de três dígitos.');
        }
        if (!in_array($request['languageMessages'], ['pt', 'en', 'fr'], true)) {
            throw new SispVinti4NetException('LanguageMessages deve ser pt, en ou fr.');
        }
        $this->validateHttpsUrl($request['urlMerchantResponse'], true);
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $request['timeStamp']);
        if (!$timestamp || $timestamp->format('Y-m-d H:i:s') !== $request['timeStamp']) {
            throw new SispVinti4NetException('TimeStamp inválido; use Y-m-d H:i:s.');
        }
        if (in_array($request['transactionCode'], ['2', '3'], true)) {
            if (!preg_match('/^\d{1,5}$/', $request['entityCode'])
                || !preg_match('/^\d{1,9}$/', $request['referenceNumber'])) {
                throw new SispVinti4NetException('EntityCode e ReferenceNumber devem ser numéricos e respeitar os limites SISP.');
            }
        }
        if ($request['transactionCode'] === '4') {
            if ($request['currency'] !== '132') {
                throw new SispVinti4NetException('Currency para estorno deve ser 132.');
            }
            if (!preg_match('/^[A-Za-z0-9]{1,8}$/', $request['transactionID'])
                || !preg_match('/^\d{1,4}$/', $request['clearingPeriod'])) {
                throw new SispVinti4NetException('TransactionID ou ClearingPeriod de estorno inválido.');
            }
        }
        if ($tokenOperation) {
            $code = $request['transactionCode'];
            if ($code !== '5' && trim($request['token']) === '') {
                throw new SispVinti4NetException('Token não pode estar vazio.');
            }
            if ($code === '5' && $request['token'] !== '') {
                throw new SispVinti4NetException('Enrollment não deve incluir um token existente.');
            }
            if ($code === '7' && $request['amount'] !== '0') {
                throw new SispVinti4NetException('Cancelamento de token exige amount zero.');
            }
            if ($request['currency'] !== '132') {
                throw new SispVinti4NetException('Tokenização exige currency 132.');
            }
            if (!in_array($request['languageMessages'], ['pt', 'en'], true)) {
                throw new SispVinti4NetException('Tokenização suporta apenas os idiomas pt e en.');
            }
        }
    }

    /** @param array<string, mixed> $request */
    private function fingerprintPaymentRequest(array $request): string
    {
        $entity = $request['entityCode'] !== '' ? (int) $request['entityCode'] : '';
        $reference = $request['referenceNumber'] !== '' ? (int) $request['referenceNumber'] : '';
        $message = $this->encodedAuthCode()
            . $request['timeStamp']
            . $this->amountToLong($request['amount'])
            . $request['merchantRef']
            . $request['merchantSession']
            . $request['posID']
            . $request['currency']
            . $request['transactionCode']
            . $entity
            . $reference;
        return base64_encode(hash('sha512', $message, true));
    }

    /** @param array<string, mixed> $request */
    private function fingerprintTokenRequest(array $request): string
    {
        $message = $this->encodedAuthCode()
            . $request['timeStamp']
            . $this->amountToLong($request['amount'])
            . $request['merchantRef']
            . trim($request['merchantSession'])
            . $request['posID']
            . trim($request['currency'])
            . trim($request['transactionCode']);
        foreach (['entityCode', 'referenceNumber'] as $field) {
            if ($request[$field] !== '') {
                $message .= $this->numericDigits($request[$field]);
            }
        }
        $message .= $request['token'];
        return base64_encode(hash('sha512', $message, true));
    }

    /** @param array<string, mixed> $data */
    private function fingerprintPaymentResponse(array $data): string
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

    /** @param array<string, mixed> $data */
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
                ? (preg_replace('/\s+/u', '', $value) ?? '')
                : $value;
        }
        return base64_encode(hash('sha512', $message, true));
    }

    /** @param array<string, mixed> $data */
    private function fingerprintTokenResponse(array $data): string
    {
        $message = $this->encodedAuthCode();
        foreach (['messageType', 'merchantRespMerchantRef', 'merchantRespMerchantSession', 'merchantRespMessageID', 'merchantResp', 'merchantRespCP', 'merchantRespTid'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $message .= in_array($field, ['merchantRespCP', 'merchantRespTid'], true)
                ? str_replace(' ', '', $value)
                : $value;
        }
        if ($data['messageType'] === 'A') {
            $message .= $data['merchantRespTimeStamp']
                . $data['token']
                . trim($data['tokenDescription'])
                . $this->amountToLong($data['maxAmountAllowed'])
                . trim($data['maxNumberOfTransactions'])
                . str_replace('-', '', $data['limitDate'])
                . substr(trim($data['merchantRespPan']), -4);
        } else {
            $message .= trim((string) ($data['approvalCode'] ?? ''))
                . $this->amountToLong($data['merchantRespPurchaseAmount'])
                . $data['merchantRespTimeStamp'];
            foreach (['merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'] as $field) {
                $message .= trim((string) ($data[$field] ?? ''));
            }
        }
        return base64_encode(hash('sha512', $message, true));
    }

    /** @param array<string, mixed> $data */
    private function validateTokenResponse(array $data, ?string $expectedCode): void
    {
        if (!in_array($data['messageType'] ?? '', ['A', 'B', 'C'], true)
            || ($data['resultFingerPrintVersion'] ?? '') !== '1') {
            throw new SispVinti4NetException('Tipo ou versão de callback de token inválido.');
        }
        $required = [
            'merchantRespMerchantRef', 'merchantRespMerchantSession', 'merchantRespMessageID',
            'merchantResp', 'merchantRespCP', 'merchantRespTid', 'merchantRespTimeStamp',
        ];
        $specific = $data['messageType'] === 'A'
            ? ['token', 'tokenDescription', 'maxAmountAllowed', 'maxNumberOfTransactions', 'limitDate', 'merchantRespPan']
            : ['merchantRespPurchaseAmount'];
        if (in_array($expectedCode, ['2', '3'], true)) {
            $required = array_merge($required, ['merchantRespReferenceNumber', 'merchantRespEntityCode']);
        }
        foreach (array_merge($required, $specific) as $field) {
            if (!isset($data[$field]) || !is_string($data[$field])
                || (in_array($field, $required, true) && trim($data[$field]) === '')) {
                throw new SispVinti4NetException("Campo de callback de token inválido: {$field}.");
            }
        }
        foreach (['approvalCode', 'merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'] as $field) {
            if (isset($data[$field]) && !is_string($data[$field])) {
                throw new SispVinti4NetException("Campo de callback de token inválido: {$field}.");
            }
        }
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['merchantRespTimeStamp']);
        if (!$timestamp || $timestamp->format('Y-m-d H:i:s') !== $data['merchantRespTimeStamp']) {
            throw new SispVinti4NetException('Timestamp de callback de token inválido.');
        }
        if ($data['messageType'] === 'A') {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $data['limitDate']);
            if (trim($data['token']) === '' || !$date || $date->format('Y-m-d') !== $data['limitDate']
                || !preg_match('/^\d+$/', $data['maxNumberOfTransactions'])
                || !preg_match('/\d{4}$/', $data['merchantRespPan'])) {
                throw new SispVinti4NetException('Dados de enrollment inválidos.');
            }
        }
    }

    private function amountToLong(mixed $amount): string
    {
        $value = trim((string) ($amount ?? '0'));
        if (!preg_match('/^(\d+)(?:\.(\d{1,3}))?$/', $value, $matches)) {
            throw new SispVinti4NetException('Amount de resposta inválido.');
        }
        $integer = ltrim($matches[1], '0') ?: '0';
        $result = ltrim($integer . str_pad($matches[2] ?? '', 3, '0'), '0');
        return $result === '' ? '0' : $result;
    }

    private function numericDigits(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^\d+$/', $value)) {
            throw new SispVinti4NetException('Entidade ou referência inválida.');
        }
        return ltrim($value, '0') ?: '0';
    }

    private function fingerprintNumber(mixed $value, bool $numeric = true): string
    {
        $value = preg_replace('/\s+/u', '', (string) ($value ?? '')) ?? '';
        if ($value === '') {
            return '';
        }
        if ($numeric && !ctype_digit($value)) {
            throw new SispVinti4NetException('Campo numérico inválido no callback.');
        }
        return ltrim($value, '0') ?: '0';
    }

    private function encodedAuthCode(): string
    {
        return base64_encode(hash('sha512', $this->posAuthCode, true));
    }

    /** @param array<string, mixed> $fields */
    private function buildPostUrl(array $fields): string
    {
        if ($this->endpoint !== null) {
            $url = $this->endpoint;
        } else {
            $code = $fields['transactionCode'];
            $path = match (true) {
                isset($fields['token']) && $code === '5' => '/TokenEnrollment',
                isset($fields['token']) && $code === '7' => '/TokenCancel',
                isset($fields['token']) => '/TokenPayment',
                default => '/CardPayment',
            };
            $url = rtrim(self::DEFAULT_BASE_URL, '/') . $path;
        }
        $this->validateHttpsUrl($url);
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . http_build_query([
            'FingerPrint' => $fields['fingerprint'],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion'],
        ]);
    }

    private function validateHttpsUrl(string $url, bool $allowLocalHttp = false): void
    {
        $isLocal = in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), ['localhost', '127.0.0.1', '[::1]'], true);
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $validScheme = $scheme === 'https' || ($allowLocalHttp && $scheme === 'http' && $isLocal);
        if (filter_var($url, FILTER_VALIDATE_URL) === false || !$validScheme
            || parse_url($url, PHP_URL_FRAGMENT) !== null
            || parse_url($url, PHP_URL_USER) !== null
            || parse_url($url, PHP_URL_PASS) !== null) {
            throw new SispVinti4NetException('A URL de callback/endpoint deve ser HTTPS e não pode conter fragmento.');
        }
    }
}
