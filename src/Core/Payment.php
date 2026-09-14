<?php

declare(strict_types=1);

namespace Erilshk\Sisp\Core;

use Erilshk\Sisp\Billing;
use Erilshk\Sisp\Exceptions\Vinti4Exception;

/**
 * Classe responsável por operações de Pagamento com o SISP.
 * Inclui compras 3DS, serviços e recargas.
 */
class Payment extends Sisp
{
    private const ENDPOINT_PATH = '/CardPayment';


    /**
     * Gera fingerprint para requisição de pagamento.
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
     * Gera fingerprint esperado na resposta do SISP.
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
     * Normalize Billing instances, SISP fields and legacy `user` data.
     *
     * @param array<string, mixed> $billing
     *
     * @return array<string, mixed>
     */
    protected function normalizeBilling(array $billing): array
    {
        $user = $billing['user'] ?? [];
        unset($billing['user']);

        if (is_object($user)) {
            $user = get_object_vars($user);
        }

        $legacy = is_array($user) && $user !== []
            ? Billing::fromUser($user)->toArray()
            : [];

        $explicit = Billing::from($billing)->toArray();
        $normalized = array_replace($legacy, $explicit);

        if (isset($legacy['acctInfo'], $explicit['acctInfo'])) {
            $normalized['acctInfo'] = array_replace(
                $legacy['acctInfo'],
                $explicit['acctInfo'],
            );
        }

        return $normalized;
    }

    /**
     * Generate the Base64-encoded 3D Secure purchase request.
     *
     * @param array<string, mixed> $billing
     */
    protected function generatePurchaseRequest(array $billing): string
    {
        $required = [
            'email',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
        ];

        $missing = array_filter(
            $required,
            static fn(string $field): bool =>
            !isset($billing[$field]) || trim((string) $billing[$field]) === '',
        );

        if ($missing !== []) {
            throw new Vinti4Exception(
                'Campos obrigatórios ausentes em billing: ' .
                    implode(', ', $missing) . '.'
            );
        }

        try {
            $json = json_encode(
                $billing,
                JSON_UNESCAPED_SLASHES |
                    JSON_UNESCAPED_UNICODE |
                    JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException $exception) {
            throw new Vinti4Exception(
                'Erro ao gerar JSON de billing.',
                0,
                $exception,
            );
        }

        return base64_encode($json);
    }


    /**
     * Prepara uma requisição de pagamento (compra, serviço, recarga).
     * 
     * @param array{
     *  transactionCode: string, 
     *  urlMerchantResponse?: string, 
     *  timeStamp?: string, 
     *  amount?: string, 
     *  currency?: string, 
     *  merchantRef?: string, 
     *  merchantSession?: string,
     *  billing: array, 
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
     * @throws Vinti4Exception
     * @return array{fields: array, postUrl: string}
     */
    public function preparePayment(array $params): array
    {
        if (empty($params['transactionCode'])) {
            throw new Vinti4Exception("transactionCode é obrigatório.");
        }

        $currencyCode = $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE);

        $request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => $this->normalizeRequestAmount($params['amount'] ?? ''),
            'currency' => $currencyCode,
            'transactionCode' => $params['transactionCode'],
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
            'timeStamp' => $params['timeStamp'] ?? date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
        ];

        // Adiciona billing se for transação de compra
        if ($params['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($params['billing'])) {
            $billing = $this->normalizeBilling($params['billing']);
            $request['purchaseRequest'] = $this->generatePurchaseRequest($billing);
        }

        if ($error = $this->validateParams($request)) {
            throw new Vinti4Exception($error);
        }

        $request['fingerprint'] = $this->fingerprintRequest($request);

        $postUrl = $this->endpoint(self::ENDPOINT_PATH) .
            '?' . http_build_query([
                'FingerPrint' => $request['fingerprint'],
                'TimeStamp' => $request['timeStamp'],
                'FingerPrintVersion' => $request['fingerprintversion'],
            ]);

        return [
            'postUrl' => $postUrl,
            'fields' => $request
        ];
    }
}
