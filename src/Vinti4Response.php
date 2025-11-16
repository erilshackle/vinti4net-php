<?php

namespace Erilshk\Vinti4Net;

use Erilshk\Vinti4Net\Core\Sisp;
use Erilshk\Vinti4Net\Traits\ReceiptRenderer;

/**
 * Classe inteligente que representa e interpreta a resposta do SISP
 * 
 * @package Erilshk\Vinti4Net
 */
class Vinti4Response
{

    use ReceiptRenderer;

    public function __construct(
        public string $status,
        public string $message,
        public bool $success,
        public array $data = [],
        public array $dcc = [],
        public array $debug = [],
        public ?string $detail = null
    ) {}

    /**
     * Construtor inteligente que interpreta o resultado do processamento
     */
    public static function fromProcessorResult(array $result): self
    {
        $data = $result['data'] ?? [];
        
        return new self(
            status: self::determineStatus($result, $data),
            message: self::determineMessage($result, $data),
            success: self::determineSuccess($result, $data),
            data: $data,
            dcc: self::extractDcc($data),
            debug: self::extractDebug($result, $data),
            detail: self::extractDetail($data)
        );
    }

    private static function determineStatus(array $result, array $data): string
    {
        // 1. Cancelamento pelo usuário
        if (($data['UserCancelled'] ?? '') === 'true') {
            return 'CANCELLED';
        }

        // 2. Sucesso com fingerprint válido
        if (($result['success'] ?? false) && ($result['fingerprint_valid'] ?? false)) {
            return 'SUCCESS';
        }

        // 3. Fingerprint inválido
        if (($result['success'] ?? false) && !($result['fingerprint_valid'] ?? false)) {
            return 'INVALID_FINGERPRINT';
        }

        // 4. Erro
        return 'ERROR';
    }

    private static function determineMessage(array $result, array $data): string
    {
        return match(self::determineStatus($result, $data)) {
            'CANCELLED' => 'Utilizador cancelou a transação.',
            'SUCCESS' => ($data['transactionCode'] ?? '') === Sisp::TRANSACTION_TYPE_REFUND 
                ? 'Reembolso processado com sucesso.' 
                : 'Transação válida.',
            'INVALID_FINGERPRINT' => 'Fingerprint inválido (verificar segurança).',
            'ERROR' => $data['merchantRespErrorDescription'] ?? 'Transação falhou.',
            default => 'Erro desconhecido na transação.'
        };
    }

    private static function determineSuccess(array $result, array $data): bool
    {
        return self::determineStatus($result, $data) === 'SUCCESS';
    }

    private static function extractDcc(array $data): array
    {
        // Só aplica a compras (transactionCode = 1)
        if (($data['transactionCode'] ?? '') !== Sisp::TRANSACTION_TYPE_PURCHASE || empty($data['merchantRespDCCData'])) {
            return ['enabled' => false];
        }

        $dcc = json_decode($data['merchantRespDCCData'], true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($dcc)) {
            return ['enabled' => false, 'error' => 'DCC inválido ou mal formatado'];
        }

        return [
            'enabled' => ($dcc['dcc'] ?? 'N') === 'Y',
            'amount' => $dcc['dccAmount'] ?? null,
            'currency' => $dcc['dccCurrency'] ?? null,
            'markup' => $dcc['dccMarkup'] ?? null,
            'rate' => $dcc['dccRate'] ?? null
        ];
    }

    private static function extractDebug(array $result, array $data): array
    {
        if (!($result['fingerprint_valid'] ?? false)) {
            return [
                'recebido' => $data['resultFingerPrint'] ?? '',
                'calculado' => '...' // Não temos acesso ao fingerprint calculado aqui
            ];
        }
        return [];
    }

    private static function extractDetail(array $data): ?string
    {
        return $data['merchantRespErrorDetail'] ?? null;
    }

    // ------------------------------------------------------------------
    // 📊 MÉTODOS AUXILIARES
    // ------------------------------------------------------------------

    /**
     * Cria uma resposta de sucesso (para testes)
     */
    public static function success(string $message = 'Transação válida.', array $data = [], array $dcc = []): self
    {
        return new self('SUCCESS', $message, true, $data, $dcc);
    }

    /**
     * Cria uma resposta de erro (para testes)
     */
    public static function error(string $message, ?string $detail = null, array $data = []): self
    {
        return new self('ERROR', $message, false, $data, [], [], $detail);
    }

    /**
     * Cria uma resposta de cancelamento (para testes)
     */
    public static function cancelled(string $message = 'Utilizador cancelou a transação.', array $data = []): self
    {
        return new self('CANCELLED', $message, false, $data);
    }

    /**
     * Cria uma resposta de fingerprint inválido (para testes)
     */
    public static function invalidFingerprint(array $debug = [], array $data = []): self
    {
        return new self(
            'INVALID_FINGERPRINT', 
            'Fingerprint inválido (verificar segurança).', 
            false, 
            $data, 
            [], 
            $debug
        );
    }

    /**
     * Converte para array
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'message' => $this->message,
            'success' => $this->success,
            'data' => $this->data,
            'dcc' => $this->dcc,
            'debug' => $this->debug,
            'detail' => $this->detail
        ];
    }

    /**
     * Converte para JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Verifica se a transação foi bem sucedida
     */
    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * Verifica se foi cancelada pelo usuário
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    /**
     * Verifica se há problema de fingerprint
     */
    public function hasInvalidFingerprint(): bool
    {
        return $this->status === 'INVALID_FINGERPRINT';
    }

    /**
     * Obtém o ID da transação se disponível
     */
    public function getTransactionId(): ?string
    {
        return $this->data['merchantRespTid'] ?? null;
    }

    /**
     * Obtém a referência do merchant
     */
    public function getMerchantRef(): ?string
    {
        return $this->data['merchantRespMerchantRef'] ?? null;
    }

    /**
     * Obtém o valor da transação
     */
    public function getAmount(): ?float
    {
        return isset($this->data['merchantRespPurchaseAmount']) 
            ? (float)$this->data['merchantRespPurchaseAmount'] 
            : null;
    }

    /**
     * Obtém a moeda da transação
     */
    public function getCurrency(): ?string
    {
        return $this->data['merchantRespCurrency'] ?? null;
    }
}