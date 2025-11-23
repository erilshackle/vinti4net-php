<?php

namespace Erilshk\Sisp\Core;

use InvalidArgumentException;

/**
 * Classe responsável por operações de Refund (estorno) com o SISP.
 */
class Refund extends Sisp
{
    /**
     * Gera o fingerprint da requisição de refund.
     * Segue a lógica SISP: apenas campos obrigatórios do estorno.
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
     * Gera o fingerprint esperado na resposta de refund.
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
     * Prepara os parâmetros de uma requisição de estorno/refund.
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
     * Campos obrigatórios:
     * - amount (inteiro)
     * - merchantRef
     * - merchantSession
     * - urlMerchantResponse (válida)
     * - clearingPeriod
     * - transactionID
     *
     * @throws InvalidArgumentException
     * @return array{fields: array, postUrl: string}
     */
    public function preparePayment(array $params): array
    {
        // Validar campos obrigatórios
        foreach (['amount', 'merchantRef', 'urlMerchantResponse', 'clearingPeriod', 'transactionID'] as $field) {
            if (empty($params[$field])) {
                throw new InvalidArgumentException("Campo obrigatório faltando: $field");
            }
        }

        // Validar amount
        if (!preg_match('/^\d+$/', (string)$params['amount'])) {
            throw new InvalidArgumentException("Amount deve ser inteiro, sem casas decimais.");
        }

        // Validar URL
        if (!filter_var($params['urlMerchantResponse'], FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException("urlMerchantResponse deve ser uma URL válida.");
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'],
            'merchantSession' => $params['merchantSession'] ?? "S" . date('YmdHms'),
            'amount' => (int)$params['amount'],
            'currency' => self::CURRENCY_CVE,
            'is3DSec' => 1,
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'urlMerchantResponse' => $params['urlMerchantResponse'],
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'entityCode' => '',
            'referenceNumber' => '',
            'reversal' => 'R', // identifica estorno
            'clearingPeriod' => $params['clearingPeriod'],
            'transactionID' => $params['transactionID'],
        ];

        // Validar params usando método existente
        if ($error = $this->validateParams($request)) {
            throw new InvalidArgumentException($error);
        }

        // Gerar fingerprint
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
