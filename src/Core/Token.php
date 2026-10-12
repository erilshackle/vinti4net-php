<?php

declare(strict_types=1);

namespace Erilshk\Sisp\Core;

use Erilshk\Sisp\Exceptions\Vinti4Exception;

/** Independent version 1 tokenization processor. */
class Token extends Sisp
{
    public const ENROLLMENT = '5';
    public const PURCHASE = '6';
    public const CANCELLATION = '7';
    public const ENDPOINT_PATHS = [
        'enrollment' => '/TokenEnrollment',
        'payment' => '/TokenPayment',
        'cancel' => '/TokenCancel',
    ];

    /**
     * Create a token processor using the standard SISP endpoint convention.
     *
     * @param string      $posID             Virtual POS identifier.
     * @param string      $posAuthCode       POS authorization code.
     * @param string|null $endpoint          Complete custom URL; null selects the operation's default route.
     * @param string      $cancelMessageType Expected cancellation callback type, C by default.
     *
     * @throws Vinti4Exception If credentials, URL or cancellation type are invalid.
     */
    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null,
        private string $cancelMessageType = 'C',
    ) {
        parent::__construct($posID, $posAuthCode, $endpoint);
        if (!in_array($cancelMessageType, ['B', 'C'], true)) {
            throw new Vinti4Exception('O tipo de resposta de cancelamento deve ser B ou C.');
        }
    }

    protected function fingerprintRequest(array $data): string
    {
        $message = $this->encodedAuthCode()
            . ($data['timeStamp'] ?? '')
            . $this->amountToLong($data['amount'] ?? null)
            . ($data['merchantRef'] ?? '')
            . trim((string) ($data['merchantSession'] ?? ''))
            . ($data['posID'] ?? '')
            . trim((string) ($data['currency'] ?? ''))
            . trim((string) ($data['transactionCode'] ?? ''));
        foreach (['entityCode', 'referenceNumber'] as $field) {
            if (isset($data[$field]) && (string) $data[$field] !== '') {
                $message .= $this->numericDigits((string) $data[$field]);
            }
        }
        // Token is opaque, including leading zeros and whitespace.
        $message .= $data['token'] ?? '';
        return base64_encode(hash('sha512', $message, true));
    }

    protected function fingerprintResponse(array $data): string
    {
        return ($data['messageType'] ?? '') === 'A'
            ? $this->fingerprintEnrollmentResponse($data)
            : $this->fingerprintPaymentResponse($data);
    }

    /** @param array<string, mixed> $data */
    protected function fingerprintEnrollmentResponse(array $data): string
    {
        $message = $this->responsePrefix($data)
            . ($data['merchantRespTimeStamp'] ?? '')
            . ($data['token'] ?? '')
            . trim((string) ($data['tokenDescription'] ?? ''))
            . $this->amountToLong($data['maxAmountAllowed'] ?? null)
            . trim((string) ($data['maxNumberOfTransactions'] ?? ''))
            . str_replace('-', '', (string) ($data['limitDate'] ?? ''))
            . substr(trim((string) ($data['merchantRespPan'] ?? '')), -4);
        return base64_encode(hash('sha512', $message, true));
    }

    /** B and C share this sequence. @param array<string, mixed> $data */
    protected function fingerprintPaymentResponse(array $data): string
    {
        $message = $this->responsePrefix($data)
            . trim((string) ($data['approvalCode'] ?? ''))
            . $this->amountToLong($data['merchantRespPurchaseAmount'] ?? null)
            . ($data['merchantRespTimeStamp'] ?? '');
        foreach (['merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'] as $field) {
            $message .= trim((string) ($data[$field] ?? ''));
        }
        return base64_encode(hash('sha512', $message, true));
    }

    /** @param array<string, mixed> $data */
    private function responsePrefix(array $data): string
    {
        $message = $this->encodedAuthCode();
        foreach (['messageType', 'merchantRespMerchantRef', 'merchantRespMerchantSession', 'merchantRespMessageID', 'merchantResp', 'merchantRespCP', 'merchantRespTid'] as $field) {
            $value = trim((string) ($data[$field] ?? ''));
            $message .= in_array($field, ['merchantRespCP', 'merchantRespTid'], true)
                ? str_replace(' ', '', $value) : $value;
        }
        return $message;
    }

    public function preparePayment(array $params): array
    {
        $code = (string) ($params['transactionCode'] ?? '');
        if (!in_array($code, ['2', '3', '5', '6', '7'], true)) {
            throw new Vinti4Exception('TransactionCode de token não suportado.');
        }
        $token = $params['token'] ?? '';
        if (!is_string($token) || ($code !== self::ENROLLMENT && trim($token) === '') || ($code === self::ENROLLMENT && $token !== '')) {
            throw new Vinti4Exception('Token deve ser uma string não vazia, exceto no enrollment.');
        }
        $amount = $code === self::CANCELLATION
            ? (string) ($params['amount'] ?? '0')
            : $this->normalizeRequestAmount($params['amount'] ?? '');
        if ($code === self::CANCELLATION && $amount !== '0') {
            throw new Vinti4Exception('Amount para cancelamento de token deve ser zero.');
        }
        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? self::generateReference(),
            'merchantSession' => trim((string) ($params['merchantSession'] ?? self::generateSession())),
            'amount' => $amount,
            'currency' => $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE),
            'transactionCode' => $code,
            'token' => $token,
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'entityCode' => in_array($code, ['2', '3'], true) ? ($params['entityCode'] ?? '') : '',
            'referenceNumber' => in_array($code, ['2', '3'], true) ? ($params['referenceNumber'] ?? '') : '',
            'timeStamp' => $params['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
        ];
        // Apply existing merchant/service rules without relaxing legacy operations.
        $validation = $request;
        $validation['transactionCode'] = in_array($code, ['2', '3'], true) ? $code : '1';
        $validation['amount'] = $code === self::CANCELLATION ? '1' : $amount;
        if ($error = parent::validateParams($validation)) {
            throw new Vinti4Exception($error);
        }
        if (in_array($code, ['2', '3'], true) && !preg_match('/^\d{1,5}$/', (string) $request['entityCode'])) {
            throw new Vinti4Exception('EntityCode de token deve ter até 5 dígitos.');
        }
        if ($request['currency'] !== self::CURRENCY_CVE) {
            throw new Vinti4Exception('Tokenização suporta apenas CVE (132).');
        }
        if (!in_array($request['languageMessages'], ['pt', 'en'], true)) {
            throw new Vinti4Exception('Tokenização suporta idiomas pt e en.');
        }
        $this->validateCallbackUrl((string) $request['urlMerchantResponse']);
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', (string) $request['timeStamp']);
        if (!$date || $date->format('Y-m-d H:i:s') !== $request['timeStamp']) {
            throw new Vinti4Exception('TimeStamp inválido.');
        }
        $request['fingerprint'] = $this->fingerprintRequest($request);
        $key = match ($code) {
            self::ENROLLMENT => 'enrollment',
            self::CANCELLATION => 'cancel',
            default => 'payment',
        };
        $url = $this->endpoint(self::ENDPOINT_PATHS[$key]);
        $this->requireHttps($url);
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query([
            'FingerPrint' => $request['fingerprint'],
            'TimeStamp' => $request['timeStamp'],
            'FingerPrintVersion' => '1',
        ]);
        return ['fields' => $request, 'postUrl' => $url];
    }

    /** Optional expected code comes from the merchant's persisted request, never the callback. */
    public function processResponse(array $postData, ?string $expectedCode = null): array
    {
        if ($expectedCode !== null && !in_array($expectedCode, ['2', '3', '5', '6', '7'], true)) {
            throw new Vinti4Exception('Operação de token esperada inválida.');
        }
        $type = (string) ($postData['messageType'] ?? '');
        if ($type === '6' || $type === '') {
            $result = parent::processResponse($postData);
            if (isset($postData['userCancelled']) && !isset($postData['UserCancelled'])) {
                $result['data']['UserCancelled'] = $postData['userCancelled'];
            }
            return $result;
        }
        $calculated = null;
        $valid = false;
        try {
            $this->validateResponseFields($postData, $expectedCode);
            $calculated = $this->fingerprintResponse($postData);
            $valid = isset($postData['resultFingerPrint']) && is_string($postData['resultFingerPrint'])
                && hash_equals($calculated, $postData['resultFingerPrint']);
        } catch (Vinti4Exception $exception) {
            // Malformed signed payloads fail closed; raw fields remain available.
        }
        $expectedType = match ($expectedCode) {
            '5' => 'A', '7' => $this->cancelMessageType,
            '2', '3', '6' => 'B', default => null,
        };
        $matches = $expectedType === null || $type === $expectedType;
        if ($expectedCode === '7' || $type === 'C') {
            try {
                $matches = $matches && $this->amountToLong($postData['merchantRespPurchaseAmount'] ?? '') === '0';
            } catch (Vinti4Exception $exception) {
                $matches = false;
            }
        }
        return [
            'success' => $valid && $matches && ($postData['merchantResp'] ?? '') === '0',
            'fingerprint_valid' => $valid,
            'calculated_fingerprint' => $calculated,
            'message_type' => $type,
            'data' => $postData,
        ];
    }

    /** @param array<string, mixed> $data */
    private function validateResponseFields(array $data, ?string $expectedCode): void
    {
        if (!in_array($data['messageType'] ?? '', ['A', 'B', 'C'], true) || ($data['resultFingerPrintVersion'] ?? '') !== '1') {
            throw new Vinti4Exception('Tipo ou versão de callback de token inválido.');
        }
        $required = ['merchantRespMerchantRef', 'merchantRespMerchantSession', 'merchantRespMessageID', 'merchantResp', 'merchantRespCP', 'merchantRespTid', 'merchantRespTimeStamp'];
        $specific = $data['messageType'] === 'A'
            ? ['token', 'tokenDescription', 'maxAmountAllowed', 'maxNumberOfTransactions', 'limitDate', 'merchantRespPan']
            : ['merchantRespPurchaseAmount'];
        if (in_array($expectedCode, ['2', '3'], true)) {
            $required = array_merge($required, ['merchantRespReferenceNumber', 'merchantRespEntityCode']);
        }
        foreach (array_merge($required, $specific) as $field) {
            if (!array_key_exists($field, $data) || !is_string($data[$field]) || (in_array($field, $required, true) && trim($data[$field]) === '')) {
                throw new Vinti4Exception("Campo de callback de token inválido: {$field}.");
            }
        }
        // Optional receipt/service fields may be omitted for purchase/cancellation.
        foreach (['approvalCode', 'merchantRespClientReceipt', 'merchantRespReloadCode', 'merchantRespReferenceNumber', 'merchantRespEntityCode'] as $field) {
            if (array_key_exists($field, $data) && !is_string($data[$field])) {
                throw new Vinti4Exception("Campo de callback de token inválido: {$field}.");
            }
        }
        $timestamp = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $data['merchantRespTimeStamp']);
        if (!$timestamp || $timestamp->format('Y-m-d H:i:s') !== $data['merchantRespTimeStamp']) {
            throw new Vinti4Exception('Timestamp do callback de token inválido.');
        }
        if ($data['messageType'] === 'A') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $data['limitDate']);
            if (trim($data['token']) === '' || !$date || $date->format('Y-m-d') !== $data['limitDate']
                || !preg_match('/^\d+$/', $data['maxNumberOfTransactions']) || !preg_match('/\d{4}$/', $data['merchantRespPan'])) {
                throw new Vinti4Exception('Dados de enrollment inválidos.');
            }
        }
    }

    private function numericDigits(string $value): string
    {
        $value = trim($value);
        if (!preg_match('/^\d+$/', $value)) {
            throw new Vinti4Exception('Entidade ou referência inválida.');
        }
        return ltrim($value, '0') ?: '0';
    }

    private function requireHttps(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false || strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https' || parse_url($url, PHP_URL_FRAGMENT) !== null) {
            throw new Vinti4Exception('O endpoint/callback de token deve ser uma URL HTTPS sem fragmento.');
        }
    }

    /** HTTP loopback callbacks are a local development exception, not a SISP guarantee. */
    private function validateCallbackUrl(string $url): void
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        if (filter_var($url, FILTER_VALIDATE_URL) !== false
            && parse_url($url, PHP_URL_SCHEME) === 'http'
            && in_array($host, ['localhost', '127.0.0.1', '[::1]'], true)
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && parse_url($url, PHP_URL_FRAGMENT) === null) {
            return;
        }
        $this->requireHttps($url);
    }
}
