<?php

namespace Erilshk\Sisp\Core;

use InvalidArgumentException;

/**
 * Classe base abstrata para comunicação com o SISP.
 * Fornece estrutura e métodos utilitários para Payment e Refund.
 */
abstract class Sisp
{
    // ---------------------------------------------------------------------
    // Atributos comuns
    // ---------------------------------------------------------------------
    protected string $posID;
    protected string $posAuthCode;
    protected string $baseUrl;

    // ---------------------------------------------------------------------
    // Constantes
    // ---------------------------------------------------------------------
    public const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE  = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND   = '4';

    public const CURRENCY_CVE = '132';
    public const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    // ---------------------------------------------------------------------
    // Métodos abstratos
    // ---------------------------------------------------------------------
    abstract protected function fingerprintRequest(array $data): string;
    abstract protected function fingerprintResponse(array $data): string;
    abstract public function preparePayment(array $params): array;

    // ---------------------------------------------------------------------
    // Construtor
    // ---------------------------------------------------------------------
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ---------------------------------------------------------------------
    // Conversão de moeda
    // ---------------------------------------------------------------------
    protected function currencyToCode(string $currency): int
    {
        return match (strtoupper($currency)) {
            'CVE' => 132,
            'USD' => 840,
            'EUR' => 978,
            'BRL' => 986,
            'GBP' => 826,
            'JPY' => 392,
            default =>
            is_numeric($currency)
                ? (int)$currency
                : throw new InvalidArgumentException("Invalid currency: $currency"),
        };
    }

    // ---------------------------------------------------------------------
    // PROCESS RESPONSE (Simplificado)
    // ---------------------------------------------------------------------
    public function processResponse(array $postData): array
    {
        $expected = $this->fingerprintResponse($postData);
        $received = $postData['resultFingerPrint'] ?? null;

        $fingerprintOk = hash_equals($expected, (string)$received);
        $messageType = $postData['messageType'] ?? '';

        $success = $fingerprintOk && in_array($messageType, self::SUCCESS_MESSAGE_TYPES, true);

        return [
            'success' => $success,
            'fingerprint_valid' => $fingerprintOk,
            'message_type' => $messageType,
            'data' => $postData, // Dados brutos para o Vinti4Response interpretar
        ];
    }

    /**
     * Normaliza dados de billing e user no formato completo do SISP (3DS)
     */
    protected function normalizeBilling(array $billing): array
    {
        $user = $billing['user'] ?? [];
        unset($billing['user']);

        // Helpers
        $get = fn($src, $key, $default = null) =>
        is_array($src) ? ($src[$key] ?? $default) : ($src->$key ?? $default);

        $extractCC = fn(string $phone, string $default = '238'): string =>
        preg_match('/^(?:\+|00)?(\d{1,3})/', preg_replace('/\D+/', '', $phone), $m)
            ? $m[1] : $default;

        // Campos básicos
        $email    = $billing['email'] ?? $get($user, 'email');
        $country  = $billing['billAddrCountry'] ?? $get($user, 'country');
        $city     = $billing['billAddrCity']  ?? $get($user, 'city', '');
        $line1    = $billing['billAddrLine1'] ?? $get($user, 'address', '');
        $line2    = $billing['billAddrLine2'] ?? $get($user, 'address2', '');
        $line3    = $billing['billAddrLine3'] ?? $get($user, 'address3', '');
        $postcode = $billing['billAddrPostCode'] ?? $get($user, 'postCode', '');
        $state    = $billing['billAddrState'] ?? $get($user, 'state');

        // Telefones
        $mobile = $billing['mobilePhone'] ?? $get($user, 'mobilePhone', $get($user, 'phone'));
        $work   = $billing['workPhone'] ?? $get($user, 'workPhone');

        $mobilePhone = $mobile ? [
            'cc' => $get($user, 'mobilePhoneCC', $extractCC($mobile)),
            'subscriber' => preg_replace('/\D+/', '', $mobile)
        ] : null;

        $workPhone = $work ? [
            'cc' => $get($user, 'workPhoneCC', $extractCC($work)),
            'subscriber' => preg_replace('/\D+/', '', $work)
        ] : null;

        // Dados de conta (3DS)
        $acctID      = $get($user, 'id');
        $createdAt   = $get($user, 'created_at');
        $updatedAt   = $get($user, 'updated_at');
        $suspicious  = $get($user, 'suspicious');
        $chAccAgeInd = $get($user, 'chAccAgeInd', ($createdAt ? '05' : '01'));
        $chAccPwInd  = $get($user, 'chAccPwChangeInd', ($updatedAt ? '05' : '01'));

        $acctInfo = [
            'chAccAgeInd' => $chAccAgeInd,
            'chAccChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
            'chAccDate' => $createdAt ? date('Ymd', strtotime($createdAt)) : '',
            'chAccPwChange' => $updatedAt ? date('Ymd', strtotime($updatedAt)) : '',
            'chAccPwChangeInd' => $chAccPwInd,
            'suspiciousAccActivity' => isset($suspicious) ? ($suspicious ? '02' : '01') : ''
        ];
        $acctInfo = array_filter($acctInfo, fn($v) => $v !== '');

        // Montagem final
        $data = array_filter([
            'email'            => $email,
            'billAddrCountry'  => $country,
            'billAddrCity'     => $city,
            'billAddrLine1'    => $line1,
            'billAddrLine2'    => $line2,
            'billAddrLine3'    => $line3,
            'billAddrPostCode' => $postcode,
            'billAddrState'    => $state,
            'mobilePhone'      => $mobilePhone,
            'workPhone'        => $workPhone,
            'acctID'           => $acctID,
            'acctInfo'         => !empty($acctInfo) ? $acctInfo : null,
        ], fn($v) => $v !== null && $v !== '');

        return $data;
    }

    /**
     * Gera campo purchaseRequest codificado em Base64
     */
    protected function generatePurchaseRequest(array $billing): string
    {
        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];

        $billing = $this->normalizeBilling($billing);

        $missingFields = array_diff($required, array_keys(array_filter($billing)));
        if (!empty($missingFields)) {
            $missingList = implode(', ', $missingFields);
            throw new InvalidArgumentException("Campos obrigatórios ausentes em billing: {$missingList}.");
        }

        $json = json_encode($billing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException("Erro ao gerar JSON de billing.");
        }

        return base64_encode($json);
    }

    /**
     * Valida qualquer array de parâmetros de pagamento/refund
     * Retorna a primeira mensagem de erro encontrada, ou null se válido
     *
     * @param array $params
     * @return string|null
     */
    protected function validateParams(array $params): ?string
    {
        $rules = [
            // Tipo de transação: Pagamento (1,2,3) ou Estorno (4)
            'transactionCode' => fn($v) => in_array($v, ['1', '2', '3', '4'], true) ? null : "TransactionCode inválido. Valores permitidos: 1,2,3,4.",

            // Referência do pagamento (máx 15 caracteres)
            'merchantRef' => fn($v) => strlen($v) <= 15 ? null : "MerchantRef deve ter no máximo 15 caracteres.",

            // Sessão do cliente (máx 15 caracteres)
            'merchantSession' => fn($v) => strlen($v) <= 15 ? null : "MerchantSession deve ter no máximo 15 caracteres.",

            // Valor da transação: inteiro, máximo 13 dígitos
            'amount' => fn($v) => preg_match('/^\d+$/', (string)$v) && ($toolong = (strlen((string)$v) <= 13)) ? null : "Amount deve " . ($toolong ? "ter até 13 dígitos" : "ser um inteiro sem casas decimais") . ".",

            // Código da moeda: ISO 4217 de 3 dígitos. Para estorno deve ser 132 (CVE)
            'currency' => function ($v) use ($params) {
                if (($params['transactionCode'] ?? null) === '4' && $v !== '132') {
                    return "Currency para estorno deve ser '132' (CVE).";
                }
                return preg_match('/^\d{3}$/', (string)$v) ? null : "Currency deve ser um código numérico ISO 4217 de 3 dígitos.";
            },

            // URL para retorno da transação
            'urlMerchantResponse' => fn($v) => filter_var($v, FILTER_VALIDATE_URL) ? null : "UrlMerchantResponse deve ser uma URL válida.",

            // Idioma da resposta (ISO 639-1)
            'languageMessages' => fn($v) => in_array(strtolower($v), ['pt', 'en', 'fr'], true) ? null : "LanguageMessages deve ser 'pt', 'en' ou 'fr'.",

            // Código da entidade: obrigatório apenas para pagamento (2,3)
            'entityCode' => function ($v) use ($params) {
                return in_array($params['transactionCode'] ?? null, ['2', '3'], true) && empty($v)
                    ? "EntityCode é obrigatório para transactionCode 2 e 3."
                    : null;
            },

            // Número de referência: 7 a 9 dígitos
            'referenceNumber' => fn($v) => strlen($v) <= 9 ? null : "ReferenceNumber deve ter até 9 dígitos.",

            // Clearing period da transação original (até 4 dígitos)
            'clearingPeriod' => fn($v) => preg_match('/^\d{1,4}$/', (string)$v) ? null : "ClearingPeriod deve ter até 4 dígitos numéricos.",

            // ID da transação original (até 8 dígitos)
            'transactionID' => fn($v) => preg_match('/^\w{1,8}$/', (string)$v) ? null : "TransactionID deve ter até 8 dígitos numéricos.",
            # 'transactionID' => fn($v) => preg_match('/^\d{1,8}$/', (string)$v) ? null : "TransactionID deve ter até 8 dígitos numéricos.",

            // Identificador da conta do titular do cartão (até 64 caracteres)
            'acctID' => fn($v) => strlen($v) <= 64 ? null : "AcctID deve ter no máximo 64 caracteres.",
        ];


        foreach ($params as $key => $value) {
            if (isset($rules[$key])) {
                $error = $rules[$key]($value);
                if ($error !== null) {
                    return $error; // retorna o primeiro erro encontrado
                }
            }
        }

        return null; // nenhum erro
    }
}
