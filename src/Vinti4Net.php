<?php

declare(strict_types=1);

namespace Eril\Sisp;

use Eril\Sisp\Core\Payment;
use Eril\Sisp\Core\Refund;
use Eril\Sisp\Core\Sisp;
use Eril\Sisp\Exception\InvalidConfigurationException;
use Eril\Sisp\Exception\InvalidResponseException;

final class Vinti4Net
{
    private Payment $payment;
    private Refund $refund;

    /**
     * Create a Vinti4Net client.
     *
     * @throws InvalidConfigurationException
     */
    public function __construct(
        string $posId,
        string $authCode,
        ?string $endpoint = null,
    ) {
        $posId = trim($posId);
        $authCode = trim($authCode);

        if ($posId === '') {
            throw new InvalidConfigurationException('O POS ID não pode estar vazio.');
        }

        if ($authCode === '') {
            throw new InvalidConfigurationException('O código de autenticação não pode estar vazio.');
        }

        if ($endpoint !== null && filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            throw new InvalidConfigurationException('O endpoint da SISP deve ser uma URL válida.');
        }

        $this->payment = new Payment($posId, $authCode, $endpoint);
        $this->refund = new Refund($posId, $authCode, $endpoint);
    }

    /**
     * Create a purchase transaction.
     *
     * @param array<string, mixed>|Billing $billing
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

        return $this->transaction($this->payment, [
            'transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'merchantRef' => $reference,
            'currency' => $currency,
            'billing' => $billing,
        ], $session);
    }

    /**
     * Create a service payment transaction.
     */
    public function servicePayment(
        int|string $amount,
        int $entity,
        string $number,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction($this->payment, [
            'transactionCode' => Sisp::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
            'merchantRef' => $reference,
            'currency' => 'CVE',
        ], $session);
    }

    /**
     * Create a mobile recharge transaction.
     */
    public function recharge(
        int|string $amount,
        int $entity,
        string $number,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction($this->payment, [
            'transactionCode' => Sisp::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
            'merchantRef' => $reference,
            'currency' => 'CVE',
        ], $session);
    }

    /**
     * Create a refund transaction.
     */
    public function refund(
        int|string $amount,
        string $transactionId,
        string $clearingPeriod,
        string $reference,
        ?string $session = null,
    ): TransactionRequest {
        return $this->transaction($this->refund, [
            'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'merchantRef' => $reference,
            'transactionID' => $transactionId,
            'clearingPeriod' => $clearingPeriod,
        ], $session);
    }

    /**
     * Process a callback response sent by SISP.
     *
     * @param array<string, mixed> $data
     *
     * @throws InvalidResponseException
     */
    public function processResponse(array $data): Vinti4Response
    {
        if ($data === []) {
            throw new InvalidResponseException('A resposta da SISP está vazia.');
        }

        $isRefund =
            ($data['transactionCode'] ?? null) === Sisp::TRANSACTION_TYPE_REFUND ||
            ($data['messageType'] ?? null) === '10';

        $processor = $isRefund ? $this->refund : $this->payment;
        $result = $processor->processResponse($data);

        return Vinti4Response::fromProcessorResult($result);
    }

    /**
     * Create a transaction request using the selected processor.
     *
     * @param array<string, mixed> $params
     */
    private function transaction(
        Sisp $processor,
        array $params,
        ?string $session,
    ): TransactionRequest {
        if ($session !== null) {
            $params['merchantSession'] = $session;
        }

        return new TransactionRequest($processor, $params);
    }
}
