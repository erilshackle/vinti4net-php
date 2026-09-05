<?php

declare(strict_types=1);

namespace Eril\Sisp;

use Eril\Sisp\Core\Payment;
use Eril\Sisp\Core\Refund;
use Eril\Sisp\Core\Sisp;
use Eril\Sisp\Exception\InvalidConfigurationException;
use Eril\Sisp\Exception\InvalidRequestException;
use Eril\Sisp\Exception\InvalidResponseException;

final class Vinti4Net
{
    private Payment $payment;
    private Refund $refund;

    /**
     * Create a Vinti4Net client.
     *
     * @param string      $posId    POS identifier provided by SISP.
     * @param string      $authCode Authentication code provided by SISP.
     * @param string|null $endpoint Optional custom SISP gateway endpoint.
     *
     * @throws InvalidConfigurationException When the credentials or endpoint are invalid.
     */
    public function __construct(
        string $posId,
        string $authCode,
        ?string $endpoint = null,
    ) {
        $posId = trim($posId);
        $authCode = trim($authCode);

        if ($posId === '') {
            throw new InvalidConfigurationException(
                'O POS ID não pode estar vazio.'
            );
        }

        if ($authCode === '') {
            throw new InvalidConfigurationException(
                'O código de autenticação não pode estar vazio.'
            );
        }

        if (
            $endpoint !== null &&
            filter_var($endpoint, FILTER_VALIDATE_URL) === false
        ) {
            throw new InvalidConfigurationException(
                'O endpoint da SISP deve ser uma URL válida.'
            );
        }

        $this->payment = new Payment(
            $posId,
            $authCode,
            $endpoint,
        );

        $this->refund = new Refund(
            $posId,
            $authCode,
            $endpoint,
        );
    }

    /**
     * Generate a merchant reference with exactly 15 characters.
     *
     * @param string $prefix Reference prefix containing up to six
     *                       letters, numbers or hyphens.
     *
     * @return string Generated merchant reference.
     *
     * @throws InvalidRequestException When the prefix is empty, too long
     *                                 or contains unsupported characters.
     */
    public static function generateReference(
        string $prefix = 'R',
    ): string {
        return Sisp::generateReference($prefix);
    }

    /**
     * Create a 3D Secure purchase transaction.
     *
     * @param int|string $amount    Positive integer amount in CVE.
     * @param string $reference     Unique merchant reference with up to 15 characters.
     * @param Billing|array{
     *     email: string,
     *     country: string,
     *     city: string,
     *     address: string,
     *     postalCode: string
     * } $billing                   Minimum customer billing information required for 3D Secure.
     * @param string $currency      Transaction currency.
     * @param string|null $session  Optional merchant session with up to 15 characters.
     *
     * @return TransactionRequest Prepared purchase transaction.
     *
     * @throws InvalidRequestException When the transaction or billing data is invalid.
     */
    public function purchase(
        int|string $amount,
        string $reference,
        array|Billing $billing,
        string $currency = 'CVE',
        ?string $session = null,
    ): TransactionRequest {
        $billing = $billing instanceof Billing
            ? $billing->toArray()
            : Billing::from($billing)->toArray();

        return $this->transaction(
            $this->payment,
            [
                'transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE,
                'amount' => $amount,
                'merchantRef' => $reference,
                'currency' => $currency,
                'billing' => $billing,
            ],
            $session,
        );
    }

    /**
     * Create a service payment transaction.
     *
     * @param int|string  $amount    Positive integer amount in CVE.
     * @param int         $entity    Service entity code provided by SISP.
     * @param string      $number    Customer or service reference number.
     * @param string      $reference Unique merchant reference.
     * @param string|null $session   Optional merchant session.
     *
     * @return TransactionRequest Prepared service payment transaction.
     */
    public function servicePayment(
        int|string $amount,
        int $entity,
        string $number,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction(
            $this->payment,
            [
                'transactionCode' => Sisp::TRANSACTION_TYPE_SERVICE,
                'amount' => $amount,
                'entityCode' => $entity,
                'referenceNumber' => $number,
                'merchantRef' => $reference,
                'currency' => 'CVE',
            ],
            $session,
        );
    }

    /**
     * Create a mobile recharge transaction.
     *
     * @param int|string  $amount    Positive integer amount in CVE.
     * @param int         $entity    Mobile operator entity code provided by SISP.
     * @param string      $number    Mobile number or recharge reference.
     * @param string      $reference Unique merchant reference.
     * @param string|null $session   Optional merchant session.
     *
     * @return TransactionRequest Prepared mobile recharge transaction.
     */
    public function recharge(
        int|string $amount,
        int $entity,
        string $number,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction(
            $this->payment,
            [
                'transactionCode' => Sisp::TRANSACTION_TYPE_RECHARGE,
                'amount' => $amount,
                'entityCode' => $entity,
                'referenceNumber' => $number,
                'merchantRef' => $reference,
                'currency' => 'CVE',
            ],
            $session,
        );
    }

    /**
     * Create a refund transaction.
     *
     * @param int|string  $amount         Positive integer amount to refund in CVE.
     * @param string      $transactionId  SISP identifier of the original transaction.
     * @param string      $clearingPeriod Clearing period of the original transaction.
     * @param string      $reference      Unique merchant reference for the refund.
     * @param string|null $session        Optional merchant session.
     *
     * @return TransactionRequest Prepared refund transaction.
     */
    public function refund(
        int|string $amount,
        string $transactionId,
        string $clearingPeriod,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction(
            $this->refund,
            [
                'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND,
                'amount' => $amount,
                'merchantRef' => $reference,
                'transactionID' => $transactionId,
                'clearingPeriod' => $clearingPeriod,
            ],
            $session,
        );
    }

    /**
     * Process and validate a callback response sent by SISP.
     *
     * @param array<string, mixed> $data Callback payload, normally received from `$_POST`.
     *
     * @return Vinti4Response Validated and normalized SISP response.
     *
     * @throws InvalidResponseException When the callback payload is empty or invalid.
     */
    public function processResponse(
        array $data,
    ): Vinti4Response {
        if ($data === []) {
            throw new InvalidResponseException(
                'A resposta da SISP está vazia.'
            );
        }

        $isRefund =
            ($data['transactionCode'] ?? null) ===
            Sisp::TRANSACTION_TYPE_REFUND ||
            ($data['messageType'] ?? null) === '10';

        $processor = $isRefund
            ? $this->refund
            : $this->payment;

        $result = $processor->processResponse($data);

        return Vinti4Response::fromProcessorResult($result);
    }

    /**
     * Create a transaction request using the selected SISP processor.
     *
     * When no merchant session is provided, the processor generates
     * one automatically while preparing the payment form.
     *
     * @param Sisp                 $processor Payment or refund processor.
     * @param array<string, mixed> $params    Transaction parameters.
     * @param string|null          $session   Optional merchant session.
     *
     * @return TransactionRequest Prepared transaction request.
     */
    private function transaction(
        Sisp $processor,
        array $params,
        ?string $session,
    ): TransactionRequest {
        if ($session !== null) {
            $params['merchantSession'] = $session;
        }

        return new TransactionRequest(
            $processor,
            $params,
        );
    }
}
