<?php

declare(strict_types=1);

namespace Eril\Sisp\Core;

use Eril\Sisp\Exception\InvalidRequestException;

final class Payment extends Sisp
{
    /**
     * Generate the fingerprint for a payment request.
     *
     * @param array<string, mixed> $data
     */
    protected function fingerprintRequest(array $data): string
    {
        $amount = $this->fingerprintAmount(
            (string) ($data['amount'] ?? '')
        );

        $entity = !empty($data['entityCode'])
            ? (string) (int) $data['entityCode']
            : '';

        $reference = !empty($data['referenceNumber'])
            ? (string) (int) $data['referenceNumber']
            : '';

        $content =
            $this->encodedAuthorizationCode() .
            ($data['timeStamp'] ?? '') .
            $amount .
            ($data['merchantRef'] ?? '') .
            ($data['merchantSession'] ?? '') .
            ($data['posID'] ?? '') .
            ($data['currency'] ?? '') .
            ($data['transactionCode'] ?? '') .
            $entity .
            $reference;

        return base64_encode(
            hash('sha512', $content, true)
        );
    }

    /**
     * Generate the expected fingerprint for a payment response.
     *
     * @param array<string, mixed> $data
     */
    protected function fingerprintResponse(array $data): string
    {
        $amount = $this->responseFingerprintAmount(
            $data['merchantRespPurchaseAmount'] ?? null
        );

        $reference = !empty($data['merchantRespReferenceNumber'])
            ? (string) (int) $data['merchantRespReferenceNumber']
            : '';

        $entity = !empty($data['merchantRespEntityCode'])
            ? (string) (int) $data['merchantRespEntityCode']
            : '';

        $content =
            $this->encodedAuthorizationCode() .
            ($data['messageType'] ?? '') .
            ($data['merchantRespCP'] ?? '') .
            ($data['merchantRespTid'] ?? '') .
            ($data['merchantRespMerchantRef'] ?? '') .
            ($data['merchantRespMerchantSession'] ?? '') .
            $amount .
            ($data['merchantRespMessageID'] ?? '') .
            ($data['merchantRespPan'] ?? '') .
            ($data['merchantResp'] ?? '') .
            ($data['merchantRespTimeStamp'] ?? '') .
            $reference .
            $entity .
            ($data['merchantRespClientReceipt'] ?? '') .
            trim((string) (
                $data['merchantRespAdditionalErrorMessage'] ?? ''
            )) .
            ($data['merchantRespReloadCode'] ?? '');

        return base64_encode(
            hash('sha512', $content, true)
        );
    }

    /**
     * Prepare a purchase, service payment or recharge request.
     *
     * @param array<string, mixed> $params
     *
     * @return array{
     *     postUrl: string,
     *     fields: array<string, mixed>
     * }
     *
     * @throws InvalidRequestException
     */
    public function preparePayment(array $params): array
    {
        $transactionCode = (string) (
            $params['transactionCode'] ?? ''
        );

        if (!in_array($transactionCode, [
            self::TRANSACTION_TYPE_PURCHASE,
            self::TRANSACTION_TYPE_SERVICE,
            self::TRANSACTION_TYPE_RECHARGE,
        ], true)) {
            throw new InvalidRequestException(
                'Tipo de pagamento inválido.'
            );
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => trim((string) (
                $params['merchantRef'] ?? ''
            )),
            'merchantSession' => $this->merchantSession($params),
            'amount' => $this->normalizeAmount(
                $params['amount'] ?? ''
            ),
            'currency' => $this->currencyToCode(
                $params['currency'] ?? self::CURRENCY_CVE
            ),
            'transactionCode' => $transactionCode,
            'languageMessages' => strtolower(trim((string) (
                $params['languageMessages'] ?? 'pt'
            ))),
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
            'timeStamp' => $params['timeStamp']
                ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => trim((string) (
                $params['urlMerchantResponse'] ?? ''
            )),
        ];

        if ($transactionCode === self::TRANSACTION_TYPE_PURCHASE) {
            $request = $this->addBilling(
                $request,
                $params['billing'] ?? null,
            );
        }

        if ($error = $this->validateParams($request)) {
            throw new InvalidRequestException($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest(
            $request
        );

        return [
            'postUrl' => $this->buildPostUrl($request),
            'fields' => $request,
        ];
    }

    /**
     * Add normalized 3DS billing data to a purchase request.
     *
     * @param array<string, mixed> $request
     *
     * @return array<string, mixed>
     *
     * @throws InvalidRequestException
     */
    private function addBilling(
        array $request,
        mixed $billing,
    ): array {
        if (!is_array($billing) || $billing === []) {
            throw new InvalidRequestException(
                'Os dados de billing são obrigatórios para uma compra.'
            );
        }

        $request = array_merge($request, $billing);
        $request['purchaseRequest'] =
            $this->generatePurchaseRequest($billing);

        return $request;
    }

    /**
     * Resolve the merchant session or generate a default value.
     *
     * @param array<string, mixed> $params
     */
    private function merchantSession(array $params): string
    {
        $session = trim((string) (
            $params['merchantSession'] ?? ''
        ));

        return $session !== ''
            ? $session
            : 'S' . date('YmdHis');
    }
}