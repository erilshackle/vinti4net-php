<?php

namespace Erilshk\Sisp\Core;

use InvalidArgumentException;

/**
 * Classe responsável por operações de Pagamento com o SISP.
 * Inclui compras 3DS, serviços e recargas.
 */
class Payment extends Sisp
{
    /**
     * Gera fingerprint para requisição de pagamento.
     */
    protected function fingerprintRequest(array $data): string
    {
        $amount = (float)($data['amount'] ?? 0);;
        $amountLong = (int) bcmul($amount, '1000', 0);

        $entity = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $reference = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        $toHash = $encodedPOSAuthCode .
            ($data['timeStamp'] ?? '') .
            $amountLong .
            ($data['merchantRef'] ?? '') .
            ($data['merchantSession'] ?? '') .
            ($data['posID'] ?? '') .
            ($data['currency'] ?? '') .
            ($data['transactionCode'] ?? '') .
            $entity .
            $reference;

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Gera fingerprint esperado na resposta do SISP.
     */
    protected function fingerprintResponse(array $data): string
    {
        $amount = (float)($data["merchantRespPurchaseAmount"] ?? 0);
        $amountLong = (int) bcmul($amount, '1000', 0);

        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        $toHash = $encodedPOSAuthCode .
            ($data["messageType"] ?? '') .
            ($data["merchantRespCP"] ?? '') .
            ($data["merchantRespTid"] ?? '') .
            ($data["merchantRespMerchantRef"] ?? '') .
            ($data["merchantRespMerchantSession"] ?? '') .
            $amountLong .
            ($data["merchantRespMessageID"] ?? '') .
            ($data["merchantRespPan"] ?? '') .
            ($data["merchantResp"] ?? '') .
            ($data["merchantRespTimeStamp"] ?? '') .
            (!empty($data['merchantRespReferenceNumber']) ? (int)$data['merchantRespReferenceNumber'] : '') .
            (!empty($data['merchantRespEntityCode']) ? (int)$data['merchantRespEntityCode'] : '') .
            ($data["merchantRespClientReceipt"] ?? '') .
            trim($data["merchantRespAdditionalErrorMessage"] ?? '') .
            ($data["merchantRespReloadCode"] ?? '');

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Prepara uma requisição de pagamento (compra, serviço, recarga).
     * 
     * @param array{
     *  transactionCode: string, 
     *  urlMerchantResponse: string, 
     *  amount: string, 
     *  currency: string, 
     *  merchantRef?: string, 
     *  merchantSession?: string, 
     *  languageMessages?: string, 
     *  entityCode?: string, 
     *  referenceNumber?: string
     * } $params parametros da requisição. 
     * 
     * Obrigatórios:
     *  - **transactionCode**
     *  - **amount**
     *  - **urlMerchantResponse**
     * 
     * @throws \InvalidArgumentException
     * @return array{fields: array, postUrl: string}
     */
    public function preparePayment(array $params): array
    {
        if (empty($params['transactionCode'])) {
            throw new InvalidArgumentException("transactionCode é obrigatório.");
        }

        $currencyCode = $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE);

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$params['amount'],
            'currency' => $currencyCode,
            'transactionCode' => $params['transactionCode'],
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
        ];

        // Adiciona billing se for transação de compra
        if ($params['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($params['billing'])) {
            $request['billing'] = $params['billing'];
            $request = array_merge($request, $params['billing']);
            $request['purchaseRequest'] = $this->generatePurchaseRequest($params['billing']);
        }

        if($error = $this->validateParams($request)){
            throw new InvalidArgumentException($error);
        }

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
