<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

use Erilshk\Sisp\Core\Sisp;
use Erilshk\Sisp\Receipt\Receipt;

/**
 * Smart wrapper class that represents and interprets a SISP response.
 *
 * This class normalizes the raw processing result coming from SISP and exposes:
 * - A clean status (`SUCCESS`, `ERROR`, `CANCELLED`, `INVALID_FINGERPRINT`)
 * - A human-friendly message
 * - Parsed data (including DCC information)
 * - Debug information when fingerprint validation fails
 * 
 * @version 2.3.0
 * @package Erilshk\Vinti4Net
 */
class Vinti4Response
{
    public const SUCCESS = 'SUCCESS';
    public const ERROR = 'ERROR';
    public const CANCELLED = 'CANCELLED';
    public const INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';

    /**
     * Creates a structured SISP response object.
     *
     * @param string      $status   Normalized transaction status.
     * @param string      $message  Human-friendly message describing the status.
     * @param bool        $success  Indicates whether the transaction was successful.
     * @param array       $data     Raw data returned from SISP.
     * @param array       $dcc      DCC (Dynamic Currency Conversion) information if available.
     * @param array       $debug    Debug data (only populated for fingerprint errors).
     * @param string|null $detail   Optional detailed error description.
     * @param string|null $operation
     */
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly bool $success,
        public readonly array $data = [],
        public readonly array $dcc = [],
        public readonly array $debug = [],
        public readonly ?string $detail = null,
        public readonly ?string $operation = null,
    ) {}

    /**
     * Smart factory that interprets a raw processor result and creates a normalized `Vinti4Response`.
     *
     * @param array $result  Raw processor result.
     * @return self
     */
    public static function fromProcessorResult(array $result, ?string $operation = null): self
    {
        $data = $result['data'] ?? [];

        if ($operation === null) {
            $operation = match ($data['messageType'] ?? '') {
                '10' => 'refund',
                '8' => 'purchase',
                'P' => 'service_payment',
                'M' => 'recharge',
                default => $operation,
            };
        }

        return new self(
            status: self::determineStatus($result, $data),
            message: self::determineMessage($result, $data),
            success: self::determineSuccess($result, $data),
            data: $data,
            dcc: self::extractDcc($data),
            debug: self::extractDebug($result, $data),
            detail: self::extractDetail($data),
            operation: $operation
        );
    }

    /**
     * Determines the normalized transaction status based on the raw result.
     *
     * Possible values:
     * - `CANCELLED`
     * - `SUCCESS`
     * - `INVALID_FINGERPRINT`
     * - `ERROR`
     */
    private static function determineStatus(array $result, array $data): string
    {
        if (($result['fingerprint_valid'] ?? null) === false) {
            return self::INVALID_FINGERPRINT;
        }

        if (filter_var(
            $data['UserCancelled'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        )) {
            return self::CANCELLED;
        }

        if ($result['success'] ?? false) {
            return self::SUCCESS;
        }

        return self::ERROR;
    }

    /**
     * Determines the human-friendly message associated with the status.
     */
    private static function determineMessage(array $result, array $data): string
    {
        $status = self::determineStatus($result, $data);

        if ($status === self::CANCELLED) {
            return 'Utilizador cancelou a transação.';
        }

        if ($status === self::SUCCESS) {
            return ($data['transactionCode'] ?? '') === Sisp::TRANSACTION_TYPE_REFUND ||
                ($data['messageType'] ?? '') === '10'
                ? 'Reembolso processado com sucesso.'
                : 'Transação válida.';
        }

        if ($status === self::INVALID_FINGERPRINT) {
            return 'Fingerprint inválido (verificar segurança).';
        }

        foreach (
            [
                'merchantRespAdditionalErrorMessage',
                'merchantRespErrorDetail',
                'merchantRespErrorDescription',
            ] as $field
        ) {
            $message = trim((string) ($data[$field] ?? ''));

            if ($message !== '') {
                return $message;
            }
        }

        return 'Transação falhou.';
    }

    /**
     * Returns `true` if the final computed status is `SUCCESS`.
     */
    private static function determineSuccess(array $result, array $data): bool
    {
        return self::determineStatus($result, $data) === self::SUCCESS;
    }

    /**
     * Extracts DCC (Dynamic Currency Conversion) data from the SISP response.
     *
     * DCC is only applied to purchase transactions.
     *
     * @return array{
     *     enabled: bool,
     *     amount?: string|float|null,
     *     currency?: string|null,
     *     markup?: string|float|null,
     *     rate?: string|float|null,
     *     error?: string|null
     * }
     */
    private static function extractDcc(array $data): array
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

    /**
     * Extracts debug details when fingerprint validation fails.
     */
    private static function extractDebug(array $result, array $data): array
    {
        if (($result['fingerprint_valid'] ?? null) !== false) {
            return [];
        }

        return [
            'received' => (string) ($data['resultFingerPrint'] ?? ''),
            'calculated' => (string) ($result['calculated_fingerprint'] ?? ''),
        ];
    }

    /**
     * Extracts additional error details when available.
     */
    private static function extractDetail(array $data): ?string
    {
        return $data['merchantRespErrorDetail'] ?? null;
    }

    // ------------------------------------------------------------------
    // Helper Methods
    // ------------------------------------------------------------------

    /**
     * Creates a mock success response (useful for tests).
     */
    public static function success(string $message = 'Transação válida.', array $data = [], array $dcc = []): self
    {
        return new self('SUCCESS', $message, true, $data, $dcc);
    }

    /**
     * Creates a mock error response (useful for tests).
     */
    public static function error(string $message, ?string $detail = null, array $data = []): self
    {
        return new self('ERROR', $message, false, $data, [], [], $detail);
    }

    /**
     * Creates a mock cancellation response (useful for tests).
     */
    public static function cancelled(string $message = 'Utilizador cancelou a transação.', array $data = []): self
    {
        return new self('CANCELLED', $message, false, $data);
    }

    /**
     * Creates a mock invalid-fingerprint response (useful for tests).
     */
    public static function invalidFingerprint(array $debug = [], array $data = []): self
    {
        return new self(
            'INVALID_FINGERPRINT',
            'Fingerprint inválido (verificar segurança).',
            false,
            $data,
            [],
            $debug
        );
    }

    /**
     * Converts the response to an array format.
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'success' => $this->success,
            'data' => $this->safeData(),
            'dcc' => $this->dcc,
            'debug' => $this->debug,
            'detail' => $this->detail,
            'operation' => $this->operation,
        ];
    }

    /**
     * Converts the response to a pretty-printed JSON string.
     */
    public function toJson(): string
    {
        $json = json_encode(
            $this->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
        );

        return $json === false ? '{}' : $json;
    }


    /**
     * Checks whether the transaction was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Checks whether the transaction was cancelled by the user.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    /**
     * Checks whether the fingerprint was invalid.
     */
    public function hasInvalidFingerprint(): bool
    {
        return $this->status === self::INVALID_FINGERPRINT;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::ERROR;
    }

    /**
     * Returns the transaction ID if available.
     * [merchantRespTid]
     */
    public function getTransactionId(): ?string
    {
        return $this->data['merchantRespTid'] ?? null;
    }

    /**
     * Returns the Clearing Period if available.
     * [merchantRespCP]
     */
    public function getClearingPeriod(): ?string
    {
        return $this->data['merchantRespCP'] ?? null;
    }

    /**
     * Returns the merchant reference if available.
     */
    public function getMerchantRef(): ?string
    {
        $merchantRef =
            $this->data['merchantRespMerchantRef']
            ?? $this->data['merchantRef']
            ?? null;

        if ($merchantRef === null) {
            return null;
        }

        $merchantRef = trim((string) $merchantRef);

        return $merchantRef !== ''
            ? $merchantRef
            : null;
    }

    /**
     * Returns the transaction amount (converted to float).
     */
    public function getAmount(): ?float
    {
        return isset($this->data['merchantRespPurchaseAmount'])
            ? (float)$this->data['merchantRespPurchaseAmount']
            : null;
    }


    /**
     * Returns the transaction currency code (e.g., CVE, USD).
     */
    public function getCurrency(): ?string
    {
        return $this->data['merchantRespCurrency'] ?? null;
    }

    /**
     * Checks whether the Dynamic Currency Conversion is enabled or not.
     */
    public function isDccEnabled(): bool
    {
        return $this->dcc['enabled'] ?? false;
    }

    /**
     * Summary of GetAdditionalErrorMessage
     */
    public function getAdditionalErrorMessage(): string
    {
        return (string) ($this->data['merchantRespAdditionalErrorMessage'] ?? '');
    }

    /**
     * Render the default receipt or a custom PHP/HTML template.
     *
     * @param string|null $template Absolute path to a .php, .html or .htm template.
     * @param array<string, mixed> $data Custom template data.
     */
    public function renderReceipt(?string $template = null, array $data = []): string
    {
        return (new Receipt($this))->render($template, $data);
    }

    /**
     * Render the official receipt for a DCC transaction.
     *
     * @param array<string, mixed> $data Custom template data.
     */
    public function renderDccReceipt(array $data = []): string
    {
        return (new Receipt($this))->renderDcc($data);
    }

    /**
     * Render a receipt for a successful refund.
     *
     * SISP returns zero as the response amount for refunds, so the original
     * transaction amount must be supplied by the merchant application.
     *
     * @param int|string $amount Original transaction amount.
     * @param string|null $originalTransactionId Original SISP transaction ID.
     * @param array<string, mixed> $data Custom template data.
     */
    public function renderRefundReceipt(
        int|string $amount,
        ?string $originalTransactionId = null,
        array $data = [],
    ): string {
        return (new Receipt($this))->renderRefund(
            amount: $amount,
            originalTransactionId: $originalTransactionId,
            data: $data,
        );
    }

    /**
     * Generate the default HTML receipt using the legacy v2 API.
     * @deprecated v2.1
     */
    public function generateReceiptHtml(?string $companyName = null, bool $styled = true): string
    {
        return $this->renderReceipt(data: [
            'companyName' => $companyName,
            'styled' => $styled,
        ]);
    }

    /**
     * Generate a plain-text receipt using the legacy v2 API.
     * @deprecated v2.1
     */
    public function generateReceiptText(?string $companyName = null): string
    {
        return (new Receipt($this))->renderText([
            'companyName' => $companyName,
        ]);
    }

    /**
     * Returns response data with sensitive card information masked.
     *
     * @return array<string, mixed>
     */
    private function safeData(): array
    {
        $data = $this->data;

        if (isset($data['merchantRespPan'])) {
            $data['merchantRespPan'] = $this->getMaskedPan();
        }

        return $data;
    }

    /**
     * Returns the masked card number for display.
     */
    public function getMaskedPan(): ?string
    {
        $pan = trim(
            (string) ($this->data['merchantRespPan'] ?? '')
        );

        if ($pan === '' || $pan === '0') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $pan) ?? '';

        if ($digits === '') {
            return null;
        }

        return '•••• ' . substr($digits, -4);
    }
}
