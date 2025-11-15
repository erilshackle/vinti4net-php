<?php

namespace Erilshk\Vinti4Net;

use Erilshk\Vinti4Net\Core\Payment;
use Erilshk\Vinti4Net\Core\Refund;
use Erilshk\Vinti4Net\Core\Sisp;
use InvalidArgumentException;
use Exception;

/**
 * Classe principal do SDK Vinti4Net
 *
 * É a fachada (interface principal) para operações com o SISP Cabo Verde.
 * Internamente utiliza as classes Payment e Refund.
 */
class Vinti4Net
{
    private Payment $payment;
    private Refund $refund;
    private array $request = [];
    private bool $prepared = false;

    public function __construct(
        string $posID,
        string $posAuthCode,
        ?string $endpoint = null
    ) {
        $this->payment = new Payment($posID, $posAuthCode, $endpoint);
        $this->refund = new Refund($posID, $posAuthCode, $endpoint);
    }

    // ------------------------------------------------------------------
    //  ✅ SET PARAMS
    // ------------------------------------------------------------------
    public function setRequestParams(array $params): self
    {
        $allowed = [
            'merchantRef',
            'merchantSession',
            'languageMessages',
            'entityCode',
            'referenceNumber',
            'timeStamp',
            'billing',
            'currency',
            'acctID',
            'acctInfo',
            'addrMatch',
            'billAddrCountry',
            'billAddrCity',
            'billAddrLine1',
            'billAddrPostCode',
            'email',
            'clearingPeriod'
        ];

        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Parâmetro não permitido: {$key}");
            }
            $this->request[$key] = $value;
        }

        return $this;
    }

    // ------------------------------------------------------------------
    //  💳 PURCHASE PAYMENT (3DS)
    // ------------------------------------------------------------------
    public function preparePurchasePayment(float|string $amount, array $billing, string $currency = 'CVE'): static
    {
        $this->prepared = true;

        $this->request = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_PURCHASE,
            'amount' => $amount,
            'billing' => $billing,
            'currency' => $currency
        ];

        return $this;
    }


    // ------------------------------------------------------------------
    //  🧾 SERVICE PAYMENT
    // ------------------------------------------------------------------
    public function prepareServicePayment(float|string $amount, int $entity, string $number): static
    {
        $this->prepared = true;

        $this->request = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_SERVICE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ];

        return $this;
    }


    // ------------------------------------------------------------------
    //  🔄 RECHARGE PAYMENT
    // ------------------------------------------------------------------
    public function prepareRechargePayment(float|string $amount, int $entity, string $number): static
    {
        $this->prepared = true;

        $this->request = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_RECHARGE,
            'amount' => $amount,
            'entityCode' => $entity,
            'referenceNumber' => $number,
        ];

        return $this;
    }


    // ------------------------------------------------------------------
    //  💰 REFUND PAYMENT
    // ------------------------------------------------------------------
    public function prepareRefundPayment(
        float|string $amount,
        string $merchantRef,
        string $merchantSession,
        string $transactionID,
        string $clearingPeriod
    ): static {
        $this->prepared = true;

        $this->request = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'merchantRef' => $merchantRef,
            'merchantSession' => $merchantSession,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ];

        return $this;
    }


    // ------------------------------------------------------------------
    //  🧾 CREATE FORM (auto-submissão)
    // ------------------------------------------------------------------
    public function createPaymentForm(string $responseUrl, ?string $merchantRef = null): string
    {
        if (!$this->prepared) {
            throw new Exception("Nenhum pagamento preparado.");
        }

        // 1) Junta todos os parâmetros acumulados
        $params = $this->request;

        // 2) Insere parâmetros finais obrigatórios
        $params['urlMerchantResponse'] = $responseUrl;

        if ($merchantRef !== null) {
            $params['merchantRef'] = $merchantRef;
        }

        // 3) Agora prepara a requisição REAL
        $tc = $params['transactionCode'] ?? null;

        if ($tc === Sisp::TRANSACTION_TYPE_REFUND) {
            $this->request = $this->refund->preparePayment($params);
        } else {
            $this->request = $this->payment->preparePayment($params);
        }

        // 4) Obtém os campos e URL gerados pelo preparePayment
        $fields = $this->request['fields'] ?? [];
        $postUrl = $this->request['postUrl'] ?? '';

        if (empty($fields) || empty($postUrl)) {
            throw new Exception("Dados de pagamento inválidos.");
        }

        // 5) Gera os campos HTML
        $html = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) continue;
            $html .= "<input type='hidden' name='{$key}' value='" . htmlspecialchars((string)$value) . "'>\n";
        }

        // 6) HTML final
        return "
    <html>
        <head><title>Pagamento Vinti4Net</title></head>
        <body onload='document.forms[0].submit()'>
            <form method=\"post\" action=\"{$postUrl}\">
                {$html}
            </form>
            <p>processando...</p>
        </body>
    </html>";
    }


    // ------------------------------------------------------------------
    //  📥 PROCESS RESPONSE (Simplificado)
    // ------------------------------------------------------------------
    public function processResponse(array $postData): Vinti4Response
    {
        $type = ($postData['transactionCode'] ?? '') === '4' ? 'refund' : 'payment';

        $result = $type === 'refund'
            ? $this->refund->processResponse($postData)
            : $this->payment->processResponse($postData);

        // Usa o construtor inteligente
        return Vinti4Response::fromProcessorResult($result);
    }

    /**
     * Obtém dados da requisição atual (para debug)
     */
    public function getRequest(): array
    {
        return $this->request;
    }
}
