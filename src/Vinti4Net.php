<?php

declare(strict_types=1);

namespace Erilshk\Sisp;

use Erilshk\Sisp\Core\Payment;
use Erilshk\Sisp\Core\Refund;
use Erilshk\Sisp\Core\Sisp;
use Erilshk\Sisp\Core\Token;
use Erilshk\Sisp\Exceptions\Vinti4Exception;

/**
 * Main SDK facade for Vinti4Net Payments (SISP - Cabo Verde).
 *
 * Prepares payment, refund and tokenization requests, generates POST forms,
 * and validates transaction callbacks through their operation processors.
 *
 * Preparation methods are fluent: each replaces the current transaction while
 * retaining merchant options. Request validation and fingerprint generation
 * take place when createPaymentForm() is called.
 *
 * It acts as a unified interface over the internal Payment, Refund and Token
 * processing engines, simplifying all merchant-side operations.
 *
 * Supported operations:
 * - Purchase (3DS)
 * - Service payment (entity + reference)
 * - Recharge payment (entity + phone/account)
 * - Refund
 * - Token enrollment, purchase, service, recharge and cancellation
 *
 * @author  Eril TS Carvalho <erilandocarvalho@gmail.com>
 * @version 2.5
 *
 * @package Erilshk\Sisp
 * @link https://erilshackle.github.io/vinti4net-php Documentation
 */
class Vinti4Net
{
    /**
     * Processor for card purchases, service payments and recharges.
     *
     * @var Payment
     */
    private Payment $payment;

    /**
     * Processor for refunds of approved transactions.
     *
     * @var Refund
     */
    private Refund $refund;

    /**
     * Processor for token enrollment, payments and cancellation.
     *
     * @var Token
     */
    private Token $token;

    /** @var array<string,mixed> */
    private array $request = [];

    /** @var bool */
    private bool $prepared = false;

    // ------------------------------------------------------------------
    //  MERCHANT OPTIONS
    // ------------------------------------------------------------------

    /**
     * Create the payment, refund and tokenization processors.
     *
     * @param string      $posID       POS identifier supplied by SISP.
     * @param string      $posAuthCode POS authorization code supplied by SISP.
     * @param string|null $endpoint    Complete custom transaction URL; null selects the operation default.
     *
     * @throws Vinti4Exception If credentials or the custom URL are invalid.
     */
    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null
    ) {
        $this->payment = new Payment($posID, $posAuthCode, $endpoint);
        $this->refund = new Refund($posID, $posAuthCode, $endpoint);
        $this->token = new Token($posID, $posAuthCode, $endpoint);
    }

    /**
     * Set optional merchant parameters for the next transaction.
     *
     * Supported options:
     * - merchantRef: non-empty merchant reference; no fixed length is imposed.
     * - merchantSession: non-empty customer session identifier; no fixed length is imposed.
     * - languageMessages: SISP UI language; tokenization supports pt and en.
     * - timeStamp: request timestamp in Y-m-d H:i:s format.
     *
     * Options are retained when preparing another transaction. Their values are
     * validated when generating the form; unsupported keys are rejected here.
     *
     * @param array{
     *     merchantRef?: string,
     *     merchantSession?: string,
     *     languageMessages?: 'pt'|'en'|'fr',
     *     timeStamp?: string,
     *     ...
     * } $params Merchant request options.
     *
     * @return self Current instance for method chaining.
     *
     * @throws Vinti4Exception If an unsupported parameter is provided.
     */
    public function setRequestParams(array $params): self
    {
        $allowed = [
            'merchantRef',
            'merchantSession',
            'languageMessages',
            'timeStamp',
        ];

        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new Vinti4Exception("Parâmetro não permitido: {$key}");
            }
            $this->request[$key] = $value;
        }

        return $this;
    }

    /**
     * Set the merchant reference and session for the next operation.
     *
     * The reference and session must be non-empty; no fixed length is imposed.
     * Both values are validated when generating the form.
     * A session is generated when omitted. Persist both values to correlate callbacks.
     *
     * @param string      $reference Unique merchant reference.
     * @param string|null $session   Merchant session identifier.
     *
     * @return self Current instance for method chaining.
     */
    public function setMerchant(string $reference, ?string $session = null): self
    {
        return $this->setRequestParams([
            'merchantRef' => $reference,
            'merchantSession' => $session ?? Sisp::generateSession()
        ]);
    }

    /**
     * Generate a 15-character merchant reference.
     *
     * The default format is R + ymdHis + two random alphanumeric characters.
     * Random mode uses R followed by 14 hexadecimal characters. The application
     * remains responsible for enforcing reference uniqueness.
     *
     * @param bool $random Generate a fully random reference instead of the timestamp format.
     *
     * @return string Generated merchant reference.
     */
    public static function generateMerchantRef(bool $random = false): string
    {
        return Sisp::generateReference($random);
    }

    // ------------------------------------------------------------------
    //  CARD PAYMENTS AND REFUNDS
    // ------------------------------------------------------------------

    /**
     * Prepare a purchase payment with 3D Secure billing data.
     *
     * Accepts a Billing instance or an array of customer billing fields. When
     * billing data is supplied, these fields are required after normalization:
     * - email: customer email address.
     * - billAddrCountry: three-digit ISO 3166-1 numeric country code (e.g. 132).
     * - billAddrCity: billing city (e.g. Praia).
     * - billAddrLine1: first line of the billing address.
     * - billAddrPostCode: billing postal code.
     *
     * The billing payload is encoded as purchaseRequest during form generation.
     * An empty billing array omits purchaseRequest. Request validation and
     * fingerprint generation occur when createPaymentForm() is called.
     *
     * @param float|string $amount   Positive integer transaction amount.
     * @param array<string, mixed>|Billing $billing Customer billing data.
     * @param string       $currency ISO 4217 name or numeric code; defaults to CVE (132).
     *
     * @return static Current instance for method chaining.
     *
     * @see Billing
     * @see Currency
     * @see self::createPaymentForm()
     */
    public function preparePurchase(float|string $amount, array|Billing $billing, string $currency = Currency::CVE): static
    {
        $this->prepared = true;

        $request = [
            'transactionCode' =>
            Sisp::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'currency' => $currency,
        ];
        $request['billing'] = $billing instanceof Billing
            ? $billing->toArray()
            : $billing;


        $this->prepareRequest($request);

        return $this;
    }

    /**
     * Prepare a service payment using the standard card flow.
     *
     * @param float|string $amount Positive integer amount in CVE.
     * @param int          $entity SISP service entity code.
     * @param string       $number Service reference, up to 9 digits.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareServicePayment(float|string $amount, int $entity, string $number): static
    {
        $this->prepared = true;

        $this->prepareRequest([
            'transactionCode' => Sisp::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /**
     * Prepare a recharge using the standard card flow.
     *
     * @param float|string $amount Positive integer amount in CVE.
     * @param int          $entity SISP recharge entity code.
     * @param string       $number Phone/account reference, up to 9 digits.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareRecharge(float|string $amount, int $entity, string $number): static
    {
        $this->prepared = true;

        $this->prepareRequest([
            'transactionCode' => Sisp::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ]);

        return $this;
    }

    /**
     * Prepare a refund of a previous transaction.
     *
     * @param float|string $amount         Refund amount in CVE.
     * @param string       $transactionID  Original SISP transaction identifier.
     * @param string       $clearingPeriod Original clearing period.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareRefund(
        float|string $amount,
        string $transactionID,
        string $clearingPeriod
    ): static {
        $this->prepared = true;

        $this->prepareRequest([
            'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ]);

        return $this;
    }

    // ------------------------------------------------------------------
    //  TOKEN OPERATIONS
    // ------------------------------------------------------------------

    /**
     * Prepare token enrollment (transactionCode 5).
     *
     * Card details, recurring limits and OTP are collected on the SISP page.
     *
     * The minimum applies to future payments and is not debited at enrollment.
     * Generate the enrollment POST with createPaymentForm().
     *
     * @param float|string $minimumAmount Positive integer minimum amount in CVE.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareTokenRequest(float|string $minimumAmount): static
    {
        return $this->prepareTokenOperation(Token::ENROLLMENT, $minimumAmount);
    }

    /**
     * Prepare a purchase using an existing token (transactionCode 6).
     *
     * @param string       $token  Opaque token; leading zeros and original contents are preserved.
     * @param float|string $amount Positive integer amount in CVE.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareTokenPurchase(string $token, float|string $amount): static
    {
        return $this->prepareTokenOperation(Token::PURCHASE, $amount, $token);
    }

    /**
     * Prepare a service payment using a token (transactionCode 2).
     *
     * @param string       $token  Opaque token associated with the customer.
     * @param float|string $amount Positive integer amount in CVE.
     * @param int          $entity SISP service entity code, up to 5 digits.
     * @param string       $number Service reference, up to 9 digits.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareTokenServicePayment(string $token, float|string $amount, int $entity, string $number): static
    {
        return $this->prepareTokenOperation(Sisp::TRANSACTION_TYPE_SERVICE, $amount, $token, $entity, $number);
    }

    /**
     * Prepare a recharge using a token (transactionCode 3).
     *
     * @param string       $token  Opaque token associated with the customer.
     * @param float|string $amount Positive integer amount in CVE.
     * @param int          $entity SISP recharge entity code, up to 5 digits.
     * @param string       $number Phone/account reference, up to 9 digits.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareTokenRecharge(string $token, float|string $amount, int $entity, string $number): static
    {
        return $this->prepareTokenOperation(Sisp::TRANSACTION_TYPE_RECHARGE, $amount, $token, $entity, $number);
    }

    /**
     * Prepare token cancellation (transactionCode 7), with amount fixed to zero.
     *
     * A successful cancellation prevents future payments with this token.
     * This operation is distinct from a user-interrupted request.
     *
     * @param string $token Opaque token to cancel.
     *
     * @return static Current instance for method chaining.
     */
    public function prepareTokenCancellation(string $token): static
    {
        return $this->prepareTokenOperation(Token::CANCELLATION, '0', $token);
    }

    // ------------------------------------------------------------------
    //  FORM AND REQUEST DATA
    // ------------------------------------------------------------------

    /**
     * Validate the prepared operation and generate an auto-submitting POST form.
     *
     * This method prepares HTML; it does not send an HTTP request. Preparing another
     * operation replaces transaction fields while retaining merchant options.
     *
     * @param string $responseUrl Merchant callback URL; token callbacks allow HTTP loopback for local development.
     * @param string $lang        UI language; tokenization supports pt/en.
     *
     * @return string Escaped HTML form directed to the selected operation endpoint.
     *
     * @throws Vinti4Exception If no operation is prepared or request fields are invalid.
     */
    public function createPaymentForm(string $responseUrl, string $lang = 'pt'): string
    {
        if (!$this->prepared) {
            throw new Vinti4Exception("Nenhum pagamento preparado.");
        }

        $this->setRequestParams([
            'languageMessages' => $lang
        ]);

        $params = $this->request;

        $params['urlMerchantResponse'] = $responseUrl;

        $tc = $params['transactionCode'] ?? null;

        if (in_array($tc, [Token::ENROLLMENT, Token::PURCHASE, Token::CANCELLATION], true) || array_key_exists('token', $params)) {
            $prepared = $this->token->preparePayment($params);
        } elseif ($tc === Sisp::TRANSACTION_TYPE_REFUND) {
            $prepared = $this->refund->preparePayment($params);
        } else {
            $prepared = $this->payment->preparePayment($params);
        }

        $fields = $prepared['fields'] ?? [];
        $postUrl = $prepared['postUrl'] ?? '';

        if (empty($fields) || empty($postUrl)) {
            throw new Vinti4Exception("Dados de pagamento inválidos.");
        }

        $html = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) continue;
            $name = htmlspecialchars((string)$key, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $value = htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $html .= "<input type='hidden' name='{$name}' value='{$value}'>\n";
        }

        $processing = strtolower($lang) === 'pt'
            ? 'Processando...'
            : 'Processing...';

        $action = htmlspecialchars($postUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $processing = htmlspecialchars($processing, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return "
    <html>
        <head><title>Pagamento Vinti4Net</title></head>
        <body onload='document.forms[0].submit()'>
            <form method=\"post\" action=\"{$action}\">
                {$html}
            </form>
            <p>{$processing}</p>
        </body>
    </html>";
    }

    /**
     * Return the currently prepared request fields.
     *
     * Includes merchant options and operation-specific fields before request
     * normalization and fingerprint generation.
     *
     * @return array<string, mixed> Current request fields.
     */
    public function getRequest(): array
    {
        return $this->request;
    }

    // ------------------------------------------------------------------
    //  CALLBACKS
    // ------------------------------------------------------------------

    /**
     * Dispatch a callback to the payment, refund or token processor.
     *
     * Token callbacks A/B/C are routed automatically. Errors do not identify their
     * operation; use processTokenResponse() with persisted context for token flows.
     *
     * @param array<string, mixed> $postData Raw SISP POST fields.
     *
     * @return Vinti4Response Validated result; correlate reference/session before updating application state.
     *
     * @throws Vinti4Exception If the callback is empty.
     */
    public function processResponse(array $postData): Vinti4Response
    {
        return match ($postData['messageType'] ?? '') {
            'A', 'B', 'C' => $this->processTokenResponse($postData),
            '10' => $this->processRefundResponse($postData),
            '8', 'P', 'M' => $this->processPaymentResponse($postData),
            default => Vinti4Response::fromProcessorResult(
                $this->payment->processResponse($postData),
            ),
        };
    }

    /**
     * Validate a token callback against its expected operation.
     *
     * Expected operation codes:
     * - 5: enrollment, callback A.
     * - 6, 2 or 3: token purchase, service or recharge, callback B.
     * - 7: token cancellation, callback C.
     *
     * Obtain the expected code from the persisted merchant request. Success
     * requires a valid fingerprint and a matching response type and status.
     * Original callback fields remain available in the response object.
     *
     * @param array<string, mixed> $postData Raw SISP POST fields.
     * @param string|null $expectedCode Expected operation; null dispatches by callback type.
     *
     * @return Vinti4Response Validated result with token metadata when present.
     *
     * @throws Vinti4Exception If the expected operation is invalid or the callback is empty.
     */
    public function processTokenResponse(array $postData, ?string $expectedCode = null): Vinti4Response
    {
        $operation = match ($expectedCode) {
            '5' => 'token_enrollment', '7' => 'token_cancellation',
            '2', '3', '6' => 'token_payment',
            default => match ($postData['messageType'] ?? '') {
                'A' => 'token_enrollment', 'C' => 'token_cancellation',
                'B' => 'token_payment', default => 'token',
            },
        };
        return Vinti4Response::fromProcessorResult($this->token->processResponse($postData, $expectedCode), $operation);
    }

    // ------------------------------------------------------------------
    //  INTERNAL HELPERS
    // ------------------------------------------------------------------

    /**
     * Process a standard card payment callback.
     *
     * @param array<string, mixed> $postData Raw SISP POST fields.
     *
     * @return Vinti4Response
     */
    private function processPaymentResponse(
        array $postData,
    ): Vinti4Response {
        return Vinti4Response::fromProcessorResult(
            $this->payment->processResponse($postData),
            operation: 'payment',
        );
    }

    /**
     * Process a refund callback.
     *
     * @param array<string, mixed> $postData Raw SISP POST fields.
     *
     * @return Vinti4Response
     */
    private function processRefundResponse(
        array $postData,
    ): Vinti4Response {
        return Vinti4Response::fromProcessorResult(
            $this->refund->processResponse($postData),
            operation: 'refund',
        );
    }

    /**
     * Replace the current transaction with a token operation.
     *
     * @param string       $code   Token operation code.
     * @param float|string $amount Amount in CVE, zero for cancellation.
     * @param string       $token  Opaque token, empty for enrollment.
     * @param int|null     $entity Service/recharge entity.
     * @param string|null  $number Service/recharge reference.
     *
     * @return static Current instance for method chaining.
     */
    private function prepareTokenOperation(string $code, float|string $amount, string $token = '', ?int $entity = null, ?string $number = null): static
    {
        $this->prepareRequest([
            'transactionCode' => $code,
            'amount' => $amount,
            'token' => $token,
            'entityCode' => $entity ?? '',
            'referenceNumber' => $number ?? '',
        ]);
        $this->prepared = true;
        return $this;
    }

    /**
     * Replace transaction fields while retaining merchant options.
     *
     * @param array<string, mixed> $transaction Fields of the new operation.
     */
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
    }
}
