<?php

namespace Erilshk\Vinti4Net\Core;

use InvalidArgumentException;

/**
 * Classe responsável por operações de Refund (estorno) com o SISP.
 */
class Refund extends Sisp
{
    /**
     * Gera o fingerprint da requisição de refund.
     */
    protected function fingerprintRequest(array $data): string
    {
        $encoded = base64_encode(hash('sha512', $this->posAuthCode, true));

        $amount = (float)($data['amount'] ?? 0);
        $amountLong = (int)bcmul($amount, '1000', 0);

        $toHash = $encoded .
            ($data['transactionCode'] ?? '') .
            ($data['posID'] ?? '') .
            ($data['merchantRef'] ?? '') .
            ($data['merchantSession'] ?? '') .
            $amountLong .
            ($data['currency'] ?? '') .
            ($data['clearingPeriod'] ?? '') .
            ($data['transactionID'] ?? '') .
            ($data['urlMerchantResponse'] ?? '') .
            ($data['languageMessages'] ?? '') .
            ($data['timeStamp'] ?? '');

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Gera o fingerprint esperado na resposta de refund.
     */
    protected function fingerprintResponse(array $data): string
    {
        $encoded = base64_encode(hash('sha512', $this->posAuthCode, true));

        $amount = (float)($data['merchantRespPurchaseAmount'] ?? 0);
        $amountLong = (int)bcmul($amount, '1000', 0);

        $toHash = $encoded .
            ($data['messageType'] ?? '') .
            ($data['merchantRespMerchantRef'] ?? '') .
            ($data['merchantRespMerchantSession'] ?? '') .
            $amountLong .
            ($data['merchantRespMessageID'] ?? '') .
            ($data['merchantResp'] ?? '') .
            ($data['merchantRespTimeStamp'] ?? '') .
            ($data['merchantRespTransactionID'] ?? '') .
            ($data['merchantRespClearingPeriod'] ?? '');

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Prepara os parâmetros de uma requisição de estorno / reembolso / refund.
     
     * @param array{
     *  amount: int|string, 
     *  amount: string, 
     *  merchantRef: string, 
     *  merchantSession: string, 
     *  transactionID: string, 
     *  clearingPeriod: string, 
     *  urlMerchantResponse: string, 
     *  languageMessages?: string, 
     * } $params parametros da requisição. 
     * 
     * Obrigtórios:
     *  - **amount**
     *  - **merchantRef**
     *  - **merchantSession**
     *  - **urlMerchantResponse**
     *  - **clearingPeriod**
     *  - **transactionID**
     * 
     * @throws \InvalidArgumentException
     * @return array{fields: array, postUrl: string}
     */
    public function preparePayment(array $params): array 
    {
        $currencyCode = $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE);

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'],
            'merchantSession' => $params['merchantSession'],
            'amount' => (int)(float)$params['amount'],
            'currency' => $currencyCode,
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
            'clearingPeriod' => $params['clearingPeriod'],
            'transactionID' => $params['transactionID']
        ];

        $request['fingerprint'] = $this->fingerprintRequest($request);

        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $request['fingerprint'],
            'TimeStamp' => $request['timeStamp'],
            'FingerPrintVersion' => $request['fingerprintversion']
        ]);

        return [
            'postUrl' => $postUrl,
            'fields' => $request
        ];
    }
}