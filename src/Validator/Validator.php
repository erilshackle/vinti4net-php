<?php

namespace Erilshk\Vinti4Net\Validator;

class Validator
{
     // ------------------- VALIDATE PARAMS -------------------
    public static function validateParams(array $params)
    {
        $errors = [];

        foreach ($params as $key => $value) {
            $method = 'validator' . str_replace('_','',ucfirst($key));
            if (method_exists(__CLASS__, $method)) {
                // Alguns métodos usam transactionCode como contexto
                $result = in_array($key,['currency','entityCode','referenceNumber'],true)
                    ? self::$method($value, $params['transactionCode'] ?? null)
                    : self::$method($value);

                if ($result !== true) {
                    $errors[$key] = $result;
                }
            }
        }

        return $errors;
    }

    
    // ------------------- TRANSACTION CODE -------------------
    public static function validatorTransactionCode($value)
    {
        // Pagamento: 1,2,3 | Estorno: 4
        if (!in_array($value, ['1','2','3','4'], true)) {
            return "TransactionCode inválido. Valores permitidos: 1,2,3,4.";
        }
        return true;
    }

    // ------------------- POS ID -------------------
    public static function validatorPosID($value)
    {
        if (!preg_match('/^\d{1,9}$/', (string)$value)) {
            return "PosID deve ter entre 1 e 9 dígitos numéricos.";
        }
        return true;
    }

    // ------------------- MERCHANT REF -------------------
    public static function validatorMerchantRef($value)
    {
        if (strlen($value) > 15) {
            return "MerchantRef deve ter no máximo 15 caracteres.";
        }
        return true;
    }

    // ------------------- MERCHANT SESSION -------------------
    public static function validatorMerchantSession($value)
    {
        if (strlen($value) > 15) {
            return "MerchantSession deve ter no máximo 15 caracteres.";
        }
        return true;
    }

    // ------------------- AMOUNT -------------------
    public static function validatorAmount($value)
    {
        if (!preg_match('/^\d+$/', (string)$value)) {
            return "Amount deve ser um número inteiro sem casas decimais.";
        }
        if (strlen((string)$value) > 13) {
            return "Amount não pode ter mais de 13 dígitos.";
        }
        return true;
    }

    // ------------------- CURRENCY -------------------
    public static function validatorCurrency($value, $transactionCode = null)
    {
        // Refund: deve ser 132
        if ($transactionCode === '4' && $value !== '132') {
            return "Currency para estorno deve ser '132' (CVE).";
        }
        // Pagamento: qualquer código ISO 4217 de 3 dígitos
        if (!preg_match('/^\d{3}$/', (string)$value)) {
            return "Currency deve ser um código numérico ISO 4217 de 3 dígitos.";
        }
        return true;
    }

    // ------------------- URL MERCHANT RESPONSE -------------------
    public static function validatorUrlMerchantResponse($value)
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return "UrlMerchantResponse deve ser uma URL válida.";
        }
        return true;
    }

    // ------------------- LANGUAGE MESSAGES -------------------
    public static function validatorLanguageMessages($value)
    {
        if (!in_array($value, ['pt','en', 'fr'], true)) {
            return "LanguageMessages deve ser 'pt', 'en' ou 'fr'.";
        }
        return true;
    }

    // ------------------- ENTITY CODE -------------------
    public static function validatorEntityCode($value, $transactionCode = null)
    {
        // Obrigatório apenas para transactionCode 2 e 3 (pagamento)
        if (in_array($transactionCode, ['2','3'], true) && empty($value)) {
            return "EntityCode é obrigatório para transactionCode 2 e 3.";
        }
        return true;
    }

    // ------------------- REFERENCE NUMBER -------------------
    public static function validatorReferenceNumber($value)
    {
        if (strlen($value > 9 || $value < 7)) {
            return "ReferenceNumber deve ter de 7 até 9 dígitos numéricos.";
        }
        return true;
    }

    // ------------------- CLEARING PERIOD -------------------
    public static function validatorClearingPeriod($value)
    {
        if (!preg_match('/^\d{1,4}$/', (string)$value)) {
            return "ClearingPeriod deve ter até 4 dígitos numéricos.";
        }
        return true;
    }

    // ------------------- TRANSACTION ID -------------------
    public static function validatorTransactionID($value)
    {
        if (!preg_match('/^\d{1,8}$/', (string)$value)) {
            return "TransactionID deve ter até 8 dígitos numéricos.";
        }
        return true;
    }

    // ------------------- TIMESTAMP -------------------
    public static function validatorTimeStamp($value)
    {
        $dt = \DateTime::createFromFormat('Y-m-d H:i:s', $value);
        if (!$dt || $dt->format('Y-m-d H:i:s') !== $value) {
            return "TimeStamp deve estar no formato yyyy-MM-dd HH:mm:ss.";
        }
        return true;
    }

   
}
