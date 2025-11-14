<?php

namespace Erilshk\Vinti4Net\Traits;

trait ParamsValidatorTrait
{

   private $rules = [

    // Tipo de transação: Pagamento (1,2,3) ou Estorno (4)
    'transactionCode' => fn($v) => in_array($v, ['1', '2', '3', '4'], true) ? null : "TransactionCode inválido. Valores permitidos: 1,2,3,4.",

    // ID do terminal virtual (1 a 9 dígitos)
    'posID' => fn($v) => preg_match('/^\d{1,9}$/', (string)$v) ? null : "PosID deve ter entre 1 e 9 dígitos numéricos.",

    // Referência do pagamento (máx 15 caracteres)
    'merchantRef' => fn($v) => strlen($v) <= 15 ? null : "MerchantRef deve ter no máximo 15 caracteres.",

    // Sessão do cliente (máx 15 caracteres)
    'merchantSession' => fn($v) => strlen($v) <= 15 ? null : "MerchantSession deve ter no máximo 15 caracteres.",

    // Valor da transação: inteiro, máximo 13 dígitos
    'amount' => fn($v) => preg_match('/^\d+$/', (string)$v) && strlen((string)$v) <= 13 ? null : "Amount deve ser um inteiro sem casas decimais e até 13 dígitos.",

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
    'referenceNumber' => fn($v) => strlen($v) >= 7 && strlen($v) <= 9 ? null : "ReferenceNumber deve ter de 7 até 9 dígitos.",

    // Clearing period da transação original (até 4 dígitos)
    'clearingPeriod' => fn($v) => preg_match('/^\d{1,4}$/', (string)$v) ? null : "ClearingPeriod deve ter até 4 dígitos numéricos.",

    // ID da transação original (até 8 dígitos)
    'transactionID' => fn($v) => preg_match('/^\d{1,8}$/', (string)$v) ? null : "TransactionID deve ter até 8 dígitos numéricos.",

    // Timestamp da transação (yyyy-MM-dd HH:mm:ss)
    'timeStamp' => fn($v) => \DateTime::createFromFormat('Y-m-d H:i:s', $v) && (\DateTime::createFromFormat('Y-m-d H:i:s', $v)->format('Y-m-d H:i:s') === $v)
        ? null
        : "TimeStamp deve estar no formato yyyy-MM-dd HH:mm:ss.",

    // Identificador da conta do titular do cartão (até 64 caracteres)
    'acctID' => fn($v) => strlen($v) <= 64 ? null : "AcctID deve ter no máximo 64 caracteres.",

    // Email do titular do cartão
    'email' => fn($v) => filter_var($v, FILTER_VALIDATE_EMAIL) ? null : "Email inválido.",
];



    /**
     * Valida qualquer array de parâmetros de pagamento/refund
     * Retorna a primeira mensagem de erro encontrada, ou null se válido
     *
     * @param array $params
     * @return string|null
     */
    protected function validateParams(array $params): ?string
    {

        foreach ($params as $key => $value) {
            if (isset($this->rules[$key])) {
                $error = $this->rules[$key]($value);
                if ($error !== null) {
                    return $error; // retorna o primeiro erro encontrado
                }
            }
        }

        return null; // nenhum erro
    }
}
