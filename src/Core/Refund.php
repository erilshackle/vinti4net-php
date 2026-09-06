<?php

namespace Erilshk\Sisp\Core;

use Erilshk\Sisp\Exceptions\Vinti4Exception;

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
        $amountLong = $this->amountToLong($data['amount'] ?? null);

        $entity = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
        $reference = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

        $encodedPOSAuthCode = $this->encodedAuthCode();

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
        $amountLong = $this->amountToLong(
            $data['merchantRespPurchaseAmount'] ?? null
        );

        $encodedPOSAuthCode = $this->encodedAuthCode();

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
     *  merchantRef?: string, 
     *  merchantSession?: string, 
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
     * @throws Vinti4Exception
     * @return array{fields: array, postUrl: string}
     */
    public function preparePayment(array $params): array
    {
        // Validar campos obrigatórios
        foreach (['amount', 'urlMerchantResponse', 'clearingPeriod', 'transactionID'] as $field) {
            if (empty($params[$field])) {
                throw new Vinti4Exception("Campo obrigatório faltando: $field");
            }
        }

        // Validar amount
        if (!preg_match('/^\d+$/', (string)$params['amount'])) {
            throw new Vinti4Exception("Amount deve ser inteiro, sem casas decimais.");
        }

        // Validar URL
        if (!filter_var($params['urlMerchantResponse'], FILTER_VALIDATE_URL)) {
            throw new Vinti4Exception("urlMerchantResponse deve ser uma URL válida.");
        }

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => $this->normalizeRequestAmount($params['amount']),
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
            throw new Vinti4Exception($error);
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
