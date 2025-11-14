<?php

namespace Erilshk\Vinti4Net\Core;

use Erilshk\Vinti4Net\Validator\ParamsValidatorTrait;
use InvalidArgumentException;

/**
 * Classe base abstrata para comunicação com o SISP.
 * Fornece estrutura e métodos utilitários para Payment e Refund.
 */
abstract class Sisp
{
    // ---------------------------------------------------------------------
    // 🔧 Use Traits
    // ---------------------------------------------------------------------
    use ParamsValidatorTrait;

    // ---------------------------------------------------------------------
    // 🔧 Atributos comuns
    // ---------------------------------------------------------------------
    protected string $posID;
    protected string $posAuthCode;
    protected string $baseUrl;

    // ---------------------------------------------------------------------
    // ⚙️ Constantes
    // ---------------------------------------------------------------------
    public const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    public const TRANSACTION_TYPE_PURCHASE = '1';
    public const TRANSACTION_TYPE_SERVICE  = '2';
    public const TRANSACTION_TYPE_RECHARGE = '3';
    public const TRANSACTION_TYPE_REFUND   = '4';

    public const CURRENCY_CVE = '132';
    public const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    // ---------------------------------------------------------------------
    // 🧩 Métodos abstratos
    // ---------------------------------------------------------------------
    abstract protected function fingerprintRequest(array $data): string;
    abstract protected function fingerprintResponse(array $data): string;
    abstract public function preparePayment(array $params): array;

    // ---------------------------------------------------------------------
    // 🔨 Construtor
    // ---------------------------------------------------------------------
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ---------------------------------------------------------------------
    // 💱 Conversão de moeda
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
    // 📥 PROCESS RESPONSE (Simplificado)
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
}
