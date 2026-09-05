<?php

declare(strict_types=1);

namespace Eril\Sisp;

use Eril\Sisp\Core\Sisp;
use Eril\Sisp\Exception\InvalidResponseException;
use Eril\Sisp\Exception\ReceiptException;
use Eril\Sisp\Receipt\ReceiptRenderer;
use JsonException;

final class Vinti4Response
{
    public const STATUS_SUCCESS = 'SUCCESS';
    public const STATUS_ERROR = 'ERROR';
    public const STATUS_CANCELLED = 'CANCELLED';
    public const STATUS_INVALID_FINGERPRINT = 'INVALID_FINGERPRINT';

    /**
     * Create a normalized Vinti4Net response.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $dcc
     */
    private function __construct(
        private readonly string $status,
        private readonly string $message,
        private readonly bool $valid,
        private readonly bool $success,
        private readonly array $data = [],
        private readonly array $dcc = [],
        private readonly ?string $detail = null,
    ) {}

    /**
     * Create a response from a SISP processor result.
     *
     * @param array<string, mixed> $result
     *
     * @throws InvalidResponseException
     */
    public static function fromProcessorResult(array $result): self
    {
        if (
            !array_key_exists('fingerprint_valid', $result) ||
            !array_key_exists('success', $result) ||
            !isset($result['data']) ||
            !is_array($result['data'])
        ) {
            throw new InvalidResponseException(
                'O resultado do processamento da SISP é inválido.'
            );
        }

        $data = $result['data'];
        $valid = (bool) $result['fingerprint_valid'];
        $success = $valid && (bool) $result['success'];
        $status = self::determineStatus($data, $valid, $success);

        return new self(
            status: $status,
            message: self::determineMessage($status, $data),
            valid: $valid,
            success: $success,
            data: $data,
            dcc: self::extractDcc($data),
            detail: self::extractDetail($data),
        );
    }

    /**
     * Return the normalized response status.
     */
    public function status(): string
    {
        return $this->status;
    }

    /**
     * Return the normalized response message.
     */
    public function message(): string
    {
        return $this->message;
    }

    /**
     * Determine whether the response fingerprint is valid.
     */
    public function isValid(): bool
    {
        return $this->valid;
    }

    /**
     * Determine whether the transaction was successful.
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Determine whether the transaction was cancelled.
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Determine whether the transaction failed.
     */
    public function hasFailed(): bool
    {
        return $this->status === self::STATUS_ERROR;
    }

    /**
     * Determine whether the response fingerprint is invalid.
     */
    public function hasInvalidFingerprint(): bool
    {
        return $this->status === self::STATUS_INVALID_FINGERPRINT;
    }

    /**
     * Return the SISP transaction identifier.
     */
    public function transactionId(): ?string
    {
        return $this->stringValue('merchantRespTid');
    }

    /**
     * Return the transaction clearing period.
     */
    public function clearingPeriod(): ?string
    {
        return $this->stringValue('merchantRespCP');
    }

    /**
     * Return the merchant transaction reference.
     */
    public function merchantReference(): ?string
    {
        return $this->stringValue('merchantRespMerchantRef');
    }

    /**
     * Return the merchant session.
     */
    public function merchantSession(): ?string
    {
        return $this->stringValue('merchantRespMerchantSession');
    }

    /**
     * Return the transaction amount without float conversion.
     */
    public function amount(): ?string
    {
        return $this->stringValue('merchantRespPurchaseAmount');
    }

    /**
     * Return the normalized transaction currency.
     */
    public function currency(): ?string
    {
        $currency = $this->stringValue('merchantRespCurrency');

        return match ($currency) {
            '132' => 'CVE',
            '840' => 'USD',
            '978' => 'EUR',
            '986' => 'BRL',
            '826' => 'GBP',
            '392' => 'JPY',
            default => $currency,
        };
    }

    /**
     * Return the normalized transaction type.
     */
    public function transactionType(): ?string
    {
        return match ($this->stringValue('messageType')) {
            '8' => 'purchase',
            'P' => 'service',
            'M' => 'recharge',
            '10' => 'refund',

            default => match ($this->stringValue('transactionCode')) {
                Sisp::TRANSACTION_TYPE_PURCHASE => 'purchase',
                Sisp::TRANSACTION_TYPE_SERVICE => 'service',
                Sisp::TRANSACTION_TYPE_RECHARGE => 'recharge',
                Sisp::TRANSACTION_TYPE_REFUND => 'refund',
                default => null,
            },
        };
    }

    /**
     * Return additional error information from SISP.
     */
    public function additionalErrorMessage(): ?string
    {
        return $this->stringValue(
            'merchantRespAdditionalErrorMessage'
        );
    }

    /**
     * Return detailed error information from SISP.
     */
    public function detail(): ?string
    {
        return $this->detail;
    }

    /**
     * Return normalized DCC information.
     *
     * @return array<string, mixed>
     */
    public function dcc(): array
    {
        return $this->dcc;
    }

    /**
     * Return the original SISP response explicitly.
     *
     * @return array<string, mixed>
     */
    public function raw(): array
    {
        return $this->data;
    }

    /**
     * Return normalized and safe response information.
     *
     * Raw SISP data and the complete card PAN are not included.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status(),
            'message' => $this->message(),
            'valid' => $this->isValid(),
            'success' => $this->isSuccess(),
            'transactionType' => $this->transactionType(),
            'transactionId' => $this->transactionId(),
            'merchantReference' => $this->merchantReference(),
            'merchantSession' => $this->merchantSession(),
            'clearingPeriod' => $this->clearingPeriod(),
            'amount' => $this->amount(),
            'currency' => $this->currency(),
            'dcc' => $this->dcc(),
            'detail' => $this->detail(),
            'additionalErrorMessage' =>
            $this->additionalErrorMessage(),
        ];
    }

    /**
     * Return safe response information as JSON.
     *
     * @throws JsonException
     */
    public function toJson(): string
    {
        return json_encode(
            $this->toArray(),
            JSON_THROW_ON_ERROR |
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES,
        );
    }

    /**
     * Return normalized data for receipt rendering.
     *
     * @return array<string, mixed>
     */
    public function receiptData(): array
    {
        return [
            ...$this->toArray(),
            'timestamp' => $this->stringValue(
                'merchantRespTimeStamp'
            ),
            'maskedPan' => $this->maskedPan(),
            'authorizationCode' => $this->stringValue(
                'merchantRespMessageID'
            ),
            'entityCode' => $this->stringValue(
                'merchantRespEntityCode'
            ),
            'referenceNumber' => $this->stringValue(
                'merchantRespReferenceNumber'
            ),
            'clientReceipt' => $this->stringValue(
                'merchantRespClientReceipt'
            ),
            'reloadCode' => $this->stringValue(
                'merchantRespReloadCode'
            ),
        ];
    }

    /**
     * Render the transaction receipt.
     *
     * When no template is provided, the default minimal receipt
     * template bundled with the library is used.
     *
     * PHP templates receive the normalized transaction data through
     * `$receipt` and custom values through `$data`.
     *
     * HTML templates may use escaped placeholders such as
     * `{{ merchantReference }}`.
     *
     * @param string|null         $template Optional PHP, HTML or HTM template path.
     * @param array<string, mixed> $data     Custom data available to the template.
     *
     * @return string Rendered receipt HTML.
     *
     * @throws ReceiptException When the template cannot be rendered.
     */
    public function renderReceipt(
        ?string $template = null,
        array $data = [],
    ): string {
        return (new ReceiptRenderer($this))->render(
            $template,
            $data,
        );
    }

    /**
     * Render the official Dynamic Currency Conversion receipt.
     *
     * @return string Rendered DCC receipt HTML.
     *
     * @throws ReceiptException When the response does not contain
     *                          complete DCC information.
     */
    public function renderDccReceipt(): string
    {
        return (new ReceiptRenderer($this))->renderDcc();
    }

    /**
     * Create a valid successful response for tests.
     *
     * @param array<string, mixed> $data
     * @param array<string, mixed> $dcc
     */
    public static function success(
        string $message = 'Transação válida.',
        array $data = [],
        array $dcc = [],
    ): self {
        return new self(
            status: self::STATUS_SUCCESS,
            message: $message,
            valid: true,
            success: true,
            data: $data,
            dcc: $dcc,
        );
    }

    /**
     * Create a valid failed response for tests.
     *
     * @param array<string, mixed> $data
     */
    public static function error(
        string $message,
        ?string $detail = null,
        array $data = [],
    ): self {
        return new self(
            status: self::STATUS_ERROR,
            message: $message,
            valid: true,
            success: false,
            data: $data,
            detail: $detail,
        );
    }

    /**
     * Create a valid cancelled response for tests.
     *
     * @param array<string, mixed> $data
     */
    public static function cancelled(
        string $message = 'Utilizador cancelou a transação.',
        array $data = [],
    ): self {
        return new self(
            status: self::STATUS_CANCELLED,
            message: $message,
            valid: true,
            success: false,
            data: $data,
        );
    }

    /**
     * Create an invalid fingerprint response for tests.
     *
     * @param array<string, mixed> $data
     */
    public static function invalidFingerprint(
        array $data = [],
    ): self {
        return new self(
            status: self::STATUS_INVALID_FINGERPRINT,
            message: 'Fingerprint inválido.',
            valid: false,
            success: false,
            data: $data,
        );
    }



    /**
     * Determine the normalized transaction status.
     *
     * @param array<string, mixed> $data
     */
    private static function determineStatus(
        array $data,
        bool $valid,
        bool $success,
    ): string {
        if (!$valid) {
            return self::STATUS_INVALID_FINGERPRINT;
        }

        if (self::wasCancelled($data)) {
            return self::STATUS_CANCELLED;
        }

        return $success
            ? self::STATUS_SUCCESS
            : self::STATUS_ERROR;
    }

    /**
     * Determine the response message.
     *
     * @param array<string, mixed> $data
     */
    private static function determineMessage(
        string $status,
        array $data,
    ): string {
        return match ($status) {
            self::STATUS_SUCCESS => (
                ($data['transactionCode'] ?? null)
                === Sisp::TRANSACTION_TYPE_REFUND ||
                ($data['messageType'] ?? null) === '10')
                ? 'Reembolso processado com sucesso.'
                : 'Transação válida.',

            self::STATUS_CANCELLED =>
            'Utilizador cancelou a transação.',

            self::STATUS_INVALID_FINGERPRINT =>
            'Fingerprint inválido.',

            default => trim((string) (
                $data['merchantRespErrorDescription']
                ?? 'A transação falhou.'
            )),
        };
    }

    /**
     * Determine whether the customer cancelled the transaction.
     *
     * @param array<string, mixed> $data
     */
    private static function wasCancelled(array $data): bool
    {
        $value = $data['UserCancelled'] ?? false;

        return $value === true ||
            $value === 1 ||
            $value === '1' ||
            strtolower((string) $value) === 'true';
    }

    /**
     * Extract normalized DCC information.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private static function extractDcc(array $data): array
    {
        $value = $data['merchantRespDCCData'] ?? null;

        if ($value === null || $value === '') {
            return ['enabled' => false];
        }

        if (is_array($value)) {
            $dcc = $value;
        } elseif (is_string($value)) {
            try {
                $dcc = json_decode(
                    $value,
                    true,
                    flags: JSON_THROW_ON_ERROR,
                );
            } catch (JsonException) {
                return [
                    'enabled' => false,
                    'error' => 'DCC inválido ou mal formatado.',
                ];
            }
        } else {
            return [
                'enabled' => false,
                'error' => 'DCC inválido ou mal formatado.',
            ];
        }

        if (!is_array($dcc)) {
            return ['enabled' => false];
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
     * Extract detailed error information.
     *
     * @param array<string, mixed> $data
     */
    private static function extractDetail(array $data): ?string
    {
        $detail = trim((string) (
            $data['merchantRespErrorDetail'] ?? ''
        ));

        return $detail !== '' ? $detail : null;
    }

    /**
     * Return a nullable string from the raw response.
     */
    private function stringValue(string $field): ?string
    {
        if (!array_key_exists($field, $this->data)) {
            return null;
        }

        $value = $this->data[$field];

        if (!is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    /**
     * Return the masked card PAN.
     */
    private function maskedPan(): ?string
    {
        $pan = $this->stringValue('merchantRespPan');

        if ($pan === null) {
            return null;
        }

        $pan = preg_replace('/\D+/', '', $pan);

        if (strlen($pan) < 10) {
            return str_repeat('•', strlen($pan));
        }

        return substr($pan, 0, 6)
            . str_repeat('•', max(4, strlen($pan) - 10))
            . substr($pan, -4);
    }
}
