<?php
namespace Erilshk\Sisp;

use Erilshk\Sisp\Core\Payment;
use Erilshk\Sisp\Core\Refund;
use Erilshk\Sisp\Core\Sisp;
use InvalidArgumentException;
use Exception;

/**
 * Main SDK facade for Vinti4Net Payments (SISP - Cabo Verde).
 *
 * This class provides a high-level API for preparing, submitting
 * and processing Vinti4Net payment and refund transactions.
 * 
 * It acts as a unified interface over the internal Payment and Refund
 * processing engines, simplifying all merchant-side operations.
 *
 * Supported operations:
 * - Purchase (3DS)
 * - Service payment (entity + reference)
 * - Recharge payment (entity + phone/account)
 * - Refund
 *
 * @author  Eril TS Carvalho <erilandocarvalho@gmail.com>
 * @version 1.0.0
 *
 * @package Erilshk\Vinti4Net
 */
class Vinti4Net
{
    /** @var Payment */
    private Payment $payment;

    /** @var Refund */
    private Refund $refund;

    /** @var array<string,mixed> */
    private array $request = [];

    /** @var bool */
    private bool $prepared = false;

    /**
     * Constructor for the Vinti4Net facade.
     *
     * @param string      $posID        POS identifier provided by SISP.
     * @param string      $posAuthCode  POS authorization key.
     * @param string|null $endpoint     Optional custom SISP endpoint URL.
     */
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

    /**
     * Sets additional optional request parameters for the next transaction.
     *
     * Only the keys listed below are allowed:
     *
     * - **merchantRef**        Merchant reference for identifying the transaction.
     * - **merchantSession**    Unique session identifier for the merchant.
     * - **languageMessages**   Language code for SISP UI messages (e.g. "pt", "en").
     * - **entityCode**         Entity code for service or recharge payments.
     * - **referenceNumber**    Reference number for service or recharge.
     * - **timeStamp**          Optional timestamp override.
     * - **billing**            Billing section (3DS 2.x fields).
     * - **currency**           ISO currency or SISP numeric currency code.
     * - **acctID**             3DS2: Cardholder account ID.
     * - **acctInfo**           3DS2: Account info JSON block.
     * - **addrMatch**          3DS2: Indicates if billing/shipping match ("Y" or "N").
     * - **billAddrCountry**    3DS2 billing country (ISO 3166-1 alpha-2).
     * - **billAddrCity**       3DS2 billing city.
     * - **billAddrLine1**      3DS2 billing address line 1.
     * - **billAddrPostCode**   3DS2 billing postal code.
     * - **email**              Customer email.
     * - **clearingPeriod**     Required for refund operations.
     *
     * @param array{
     *     merchantRef?: string,
     *     merchantSession?: string,
     *     languageMessages?: string,
     *     entityCode?: int|string,
     *     referenceNumber?: string,
     *     timeStamp?: string,
     *     billing?: array,
     *     currency?: string|int,
     *     acctID?: string,
     *     acctInfo?: array,
     *     addrMatch?: string,
     *     billAddrCountry?: string,
     *     billAddrCity?: string,
     *     billAddrLine1?: string,
     *     billAddrPostCode?: string,
     *     email?: string,
     *     clearingPeriod?: string
     * } $params
     *
     * @return self
     *
     * @throws InvalidArgumentException If a disallowed parameter is included.
     */
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

    
    /**
     * Set the merchant reference and session. (15 chars max)
     *
     * Sets the merchant identifier/reference used by this client
     *
     * @param string      $reference Non-empty merchant reference or transaction_id. up to 15 character maximun
     * @param mixed|null  $session   Optional session information (string). up to 15 character maximun
     * @return self                  Returns $this to allow method chaining.
     */
    public function setMerchant(string $reference, ?string $session = null){
        return $this->setRequestParams([
            'merchantRef' => $reference,
            'merchantSession' => $session ?? "S" . date('YmdHms'),
        ]);
    }

    // ------------------------------------------------------------------
    //  💳 PURCHASE PAYMENT (3DS)
    // ------------------------------------------------------------------

    /**
     * Prepares a standard **purchase (3D Secure)** payment request.
     *
     * @param float|string  $amount   Transaction amount.
     * @param array|Billing $billing  Customer billing data.
     *  > __Required Params__:     
     *  -   **email**             - Customer email 
     *  -   **billAddrCountry**   - Country ISO 3166-1  (eg. 132)
     *  -   **billAddrCity**      - City (eg. Praia)
     *  -   **billAddrLine1**     - Address (eg. Avenida Cidade da Praia, 45)
     *  -   **billAddrPostCode**  - Postal Code (eg. 7600)
     * @param string        $currency ISO currency (default: CVE).
     * 
     * @return static
     */
    public function preparePurchase(float|string $amount, array|Billing $billing, string $currency = 'CVE'): static
    {
        $this->prepared = true;
        $billing = (is_object($billing) && $billing instanceof Billing)? $billing->toArray() : $billing;

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

    /**
     * Prepares a **service payment** request (entity + reference number).
     *
     * @param float|string $amount Amount to pay.
     * @param int          $entity Service entity code (SISP).
     * @param string       $number Reference number.
     *
     * @return static
     */
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

    /**
     * Prepares a **recharge payment** request (entity + phone/account number).
     *
     * @param float|string $amount Amount to pay.
     * @param int          $entity Recharge entity code.
     * @param string       $number Target account/phone number.
     *
     * @return static
     */
    public function prepareRecharge(float|string $amount, int $entity, string $number): static
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

    /**
     * Prepares a **refund** request.
     *
     * @param float|string $amount          Refund amount.
     * @param string       $merchantRef     Original merchant reference.
     * @param string       $transactionID   Original SISP transaction ID.
     * @param string       $clearingPeriod  Clearing period required by SISP.
     *
     * @return static
     */
    public function prepareRefund(
        float|string $amount,
        string $transactionID,
        string $clearingPeriod
    ): static {
        $this->prepared = true;

        $this->request = [
            'transactionCode' => Sisp::TRANSACTION_TYPE_REFUND,
            'amount' => $amount,
            'transactionID' => $transactionID,
            'clearingPeriod' => $clearingPeriod,
        ];

        return $this;
    }


    // ------------------------------------------------------------------
    //  🧾 CREATE FORM (auto-submissão)
    // ------------------------------------------------------------------

    /**
     * Generates an auto-submitting HTML form to send the transaction
     * request to the Vinti4Net gateway.
     *
     * @param string $responseUrl URL where SISP will POST the transaction result.
     * @param string $lang language Messages (default: pt).
     *
     * @return string HTML form with auto-submit enabled.
     *
     * @throws Exception If no payment has been prepared or data is invalid.
     */
    public function createPaymentForm(string $responseUrl, string $lang = 'pt'): string
    {
        if (!$this->prepared) {
            throw new Exception("Nenhum pagamento preparado.");
        }

        $this->setRequestParams([
            'languageMessages' => $lang
        ]);

        $params = $this->request;

        $params['urlMerchantResponse'] = $responseUrl;

        $tc = $params['transactionCode'] ?? null;

        if ($tc === Sisp::TRANSACTION_TYPE_REFUND) {
            $this->request = $this->refund->preparePayment($params);
        } else {
            $this->request = $this->payment->preparePayment($params);
        }

        $fields = $this->request['fields'] ?? [];
        $postUrl = $this->request['postUrl'] ?? '';

        if (empty($fields) || empty($postUrl)) {
            throw new Exception("Dados de pagamento inválidos.");
        }

        $html = '';
        foreach ($fields as $key => $value) {
            if (is_array($value)) continue;
            $html .= "<input type='hidden' name='{$key}' value='" . htmlspecialchars((string)$value) . "'>\n";
        }

        $processing = $lang == 'pt' ? 'processando...' : 'processing...';

        return "
    <html>
        <head><title>Pagamento Vinti4Net</title></head>
        <body onload='document.forms[0].submit()'>
            <form method=\"post\" action=\"{$postUrl}\">
                {$html}
            </form>
            <p>$processing>
        </body>
    </html>";
    }


    // ------------------------------------------------------------------
    //  📥 PROCESS RESPONSE (Simplificado)
    // ------------------------------------------------------------------

    /**
     * Processes the POST response sent by SISP after a payment or refund.
     *
     * Internally selects either Payment or Refund processor.
     *
     * @param array $postData Raw POST data received from SISP.
     *
     * @return Vinti4Response Standardized response object.
     */
    public function processResponse(array $postData): Vinti4Response
    {
        $type = ($postData['transactionCode'] ?? '') === '4' ? 'refund' : 'payment';

        $result = $type === 'refund'
            ? $this->refund->processResponse($postData)
            : $this->payment->processResponse($postData);

        return Vinti4Response::fromProcessorResult($result);
    }

    /**
     * Returns the currently prepared request data (for debugging).
     *
     * @return array
     */
    public function getRequest(): array
    {
        return $this->request;
    }
}
