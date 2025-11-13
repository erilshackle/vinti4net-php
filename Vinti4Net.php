<?php

namespace Erilshk\Vinti4Net;

use Exception;
use InvalidArgumentException;

/**
 * Class Vinti4Net
 *
 * SDK PHP para integração com o sistema de pagamentos **Vinti4Net** (SISP Cabo Verde, Serviço MOP021).
 * 
 * Permite criar, enviar e validar transações de diferentes tipos:
 * - Compra (3DS)
 * - Serviços (pagamentos de entidades)
 * - Recarga de telemóveis ou contas pré-pagas
 * - Reembolso
 *
 * Suporta:
 * - Autenticação 3D Secure
 * - Geração de fingerprint para validação de integridade
 * - Criação de formulários HTML auto-submissíveis
 * - Processamento e validação de callbacks do SISP
 *
 * Exemplo de uso:
 * ```php
 * - instanciar
 * - preparar um pagamento
 * - obter o formulario de pagamento
 * -
 * - processar a resposta
 * - verificar o resultado
 * ```
 *
 * @package Erilshk\Vinti4Net
 * @author  Eril TS Carvalho
 * @version 1.21.0
 * @license MIT
 */
class Vinti4Net
{
    // ------------------------------------------------------------------
    // Constantes
    // ------------------------------------------------------------------

    const DEFAULT_BASE_URL = "https://mc.vinti4net.cv/BizMPIOnUsSisp/CardPayment";

    const TRANSACTION_TYPE_PURCHASE = '1';
    const TRANSACTION_TYPE_SERVICE  = '2';
    const TRANSACTION_TYPE_RECHARGE = '3';
    const TRANSACTION_TYPE_REFUND   = '4';

    const CURRENCY_CVE = '132';
    const SUCCESS_MESSAGE_TYPES = ['8', '10', 'P', 'M'];

    // ------------------------------------------------------------------
    // Propriedades
    // ------------------------------------------------------------------

    private string $posID;
    private string $posAuthCode;
    private string $baseUrl;
    private array  $request = [];
    private bool   $prepared = false;

    // ------------------------------------------------------------------
    // Construtor
    // ------------------------------------------------------------------

    /**
     * Cria uma nova instância do cliente Vinti4Net.
     *
     * @param string $posID        Identificador do POS (fornecido pelo SISP).
     * @param string $posAuthCode  Código de autenticação do POS (fornecido pelo SISP).
     * @param string|null $endpoint URL base do endpoint SISP (para caso de testes).
     *
     * @example
     * $vinti4 = new Vinti4Net('POS123', 'ABCDEF', 'https://vinti4.sisp.cv');
     */
    public function __construct(string $posID, string $posAuthCode, ?string $endpoint = null)
    {
        $this->posID = $posID;
        $this->posAuthCode = $posAuthCode;
        $this->baseUrl = $endpoint ?? self::DEFAULT_BASE_URL;
    }

    // ------------------------------------------------------------------
    // Métodos Públicos
    // ------------------------------------------------------------------

    /**
     * Define parâmetros da requisição de pagamento.
     *
     * Somente campos explicitamente permitidos podem ser definidos.  
     * Campos não reconhecidos lançam uma exceção para evitar erros de integração.
     * Caso o campo `currency` seja informado em formato textual (ex: "CVE"), ele é convertido
     * automaticamente para o código numérico esperado pelo SISP.
     *
     * @param array{
     * merchantRef:string , merchantSession:string , languageMessages:string ,
     * entityCode:string , referenceNumber:string , timeStamp:string ,
     * billing:string , currency:string , acctID:string ,acctInfo:string ,
     * addrMatch:string , billAddrCountry:string , billAddrCity:string ,
     * billAddrLine1:string , billAddrPostCode:string ,
     * email:string ,clearingPeriod:string ,
     * } $params Lista de parâmetros (ex: ['merchantRef' => 'PEDIDO123', 'currency' => 'CVE']).
     *
     * @return self
     *
     * @throws InvalidArgumentException Se algum parâmetro não for permitido.
     */
    public function setRequestParams(array $params): self
    {
        $allowed = [
            'merchantRef', 'merchantSession', 'languageMessages',
            'entityCode', 'referenceNumber', 'timeStamp',
            'billing', 'currency', 'acctID','acctInfo',
            'addrMatch', 'billAddrCountry', 'billAddrCity',
            'billAddrLine1', 'billAddrPostCode',
            'email','clearingPeriod',
        ];

        foreach ($params as $key => $value) {
            if (!in_array($key, $allowed, true)) {
                throw new InvalidArgumentException("Parâmetro não permitido: {$key}");
            }
            if ($key == 'currency') $value = $this->currencyToCode($value);
            $this->request[$key] = $value;
        }

        return $this;
    }

    /**
     * Prepara uma requisição de pagamento do tipo **compra (3DS)**.
     *
     * Este método é utilizado para transações de compra com autenticação 3D Secure.
     * Ele normaliza os dados de billing e associa o tipo de transação correspondente.
     *
     * @param float  $amount   Valor da transação (em escudos ou outra moeda configurada).
     * @param array  $billing  Dados de faturamento e/ou do utilizador.
     * @param string $currency Código da moeda (padrão: 'CVE' ou '132').
     *
     * @return static
     *
     * @example
     * $vinti4->preparePurchasePayment(1500, ['user' => $cliente]);
     */
    public function preparePurchasePayment(float|string $amount, array $billing, string $currency = 'CVE'): static
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_PURCHASE,
            'currency' => $currency,
            'billing' => $billing,
        ]);
        return $this;
    }

    /**
     * Prepara uma requisição de pagamento do tipo **serviço**.
     *
     * Usado para pagamentos de entidades de serviços (ex: água, luz, TV).
     *
     * @param float  $amount Valor da transação.
     * @param int    $entity Código da entidade.
     * @param string $number Referência do cliente ou número de conta.
     *
     * @return static
     *
     * @example
     * $vinti4->prepareServicePayment(2500, 10001, '123456789');
     */
    public function prepareServicePayment(float|string $amount, int $entity, string $number): static
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_SERVICE,
            'entityCode' => $entity,
            'referenceNumber' => $number
        ]);
        return $this;
    }

    /**
     * Prepara uma requisição de pagamento do tipo **recarga**.
     *
     * Usado para recarregar números de telemóvel ou contas pré-pagas.
     *
     * @param float  $amount Valor da recarga.
     * @param int    $entity Código da entidade (ex: CVMovel, Unitel, etc).
     * @param string $number Número de telefone a recarregar.
     *
     * @return static
     *
     * @example
     * $vinti4->prepareRechargePayment(500, 10021, '9912345');
     */
    public function prepareRechargePayment(float|string $amount, int $entity, string $number): static
    {
        $this->preparePaymentRequest([
            'amount' => $amount,
            'transactionCode' => self::TRANSACTION_TYPE_RECHARGE,
            'entityCode' => $entity,
            'referenceNumber' => $number
        ]);
        return $this;
    }

    /**
     * Prepara uma requisição de **reembolso (refund)** no sistema Vinti4.
     *
     * Este método define a transação como um reembolso de uma operação anterior,
     * permitindo especificar a referência do comerciante, a sessão original,
     * o identificador da transação anterior e o período de liquidação.
     *
     * @param float  $amount           Valor a reembolsar.
     * @param string $merchantRef      Referência do comerciante (merchant reference).
     * @param string $merchantSession  Sessão do comerciante (merchant session).
     * @param string $transactionID    Identificador da transação original.
     * @param string $clearingPeriod   Período de liquidação (clearing period).
     *
     * @return static Instância atual para encadeamento de chamadas (fluent interface).
     *
     * @example
     * $vinti4->prepareRefundPayment(
     *     1500,
     *     'PEDIDO123',
     *     'SESSION_ABC',
     *     'TXN987654321',
     *     '2025-11'
     * );
     */

    public function prepareRefundPayment(float|string $amount, string $merchantRef, string $merchantSession, string $transactionID, string $clearingPeriod): static
    {
        $this->preparePaymentRequest([
            'transactionCode' => self::TRANSACTION_TYPE_REFUND,
            'amount'            => $amount,
            'clearingPeriod'    => $clearingPeriod,
            'transactionID'     => $transactionID,
            'merchantRef'       => $merchantRef,
            'merchantSession'   => $merchantSession,
        ]);
        return $this;
    }

    /**
     * Gera um formulário HTML auto-submissível para o ambiente Vinti4.
     *
     * Este formulário contém os campos assinados da transação e é automaticamente enviado ao SISP.
     * Deve ser usado como a etapa final antes de redirecionar o cliente para o gateway de pagamento.
     *
     * @param string      $responseUrl URL de retorno do comerciante após o pagamento.
     * @param string|null $merchantRef Referência interna opcional do comerciante.
     *
     * @return string HTML completo do formulário (pronto para renderização).
     *
     * @example
     * echo $vinti4->createPaymentForm('https://meusite.cv/pagamento/callback', 'PEDIDO123');
     */
    public function createPaymentForm(string $responseUrl, ?string $merchantRef = null): string
    {
        $this->request['urlMerchantResponse'] = $responseUrl;
        if ($merchantRef !== null) {
            $this->request['merchantRef'] = $merchantRef;
        }
        $paymentData = $this->processRequest($this->request);

        $fields = '';
        foreach ($paymentData['fields'] as $k => $v) {
            $fields .= "<input type='hidden' name='{$k}' value='" . htmlspecialchars((string)$v) . "'>\n";
        }

        return "
        <html>
        <head><title>Pagamento Vinti4Net</title></head>
        <body onload='document.forms[0].submit()'>
            <form method='post' action='{$paymentData['postUrl']}'>
                {$fields}
            </form>
            <p>processando...</p>
        </body>
        </html>";
    }

    /**
     * Processa e valida a resposta recebida do SISP após a transação.
     *
     * Este método deve ser chamado no endpoint de callback configurado em `$responseUrl`.
     * Ele valida a integridade da resposta (via fingerprint) e retorna um array padronizado
     * com o estado da transação.
     *
     * Campos retornados:
     * - `status`: SUCCESS | ERROR | CANCELLED | INVALID_FINGERPRINT  
     * - `message`: descrição amigável do estado  
     * - `success`: booleano indicando sucesso lógico  
     * - `dcc`: informações de conversão de moeda (se aplicável)  
     * - `debug`: dados técnicos de verificação  
     * - `detail`: detalhes adicionais de erro
     *
     * @param array $postData Dados recebidos via POST do SISP.
     *
     * @return array{
     * status:string,message:string, success:bool ,dcc?:array,debug?:array,detail?:string
     * } Resultado estruturado da transação.
     *
     * @example
     * $result = $vinti4->processResponse($_POST);
     * if ($result['status'] === 'SUCCESS') {
     *     // processar pagamento confirmado
     * }
     */
    public function processResponse(array $postData): array
    {
        // Inicializa o resultado padrão
        $result = [
            'status' => 'ERROR',
            'message' => 'Erro desconhecido na transação.',
            'success' => false,
            'data' => $postData,
            'dcc' => [],
            'debug' => [],
            'detail' => ''
        ];

        // 1. Cancelamento pelo usuário
        if (($postData['UserCancelled'] ?? '') === 'true') {
            $result['status'] = 'CANCELLED';
            $result['message'] = 'Utilizador cancelou a transação.';
            return $result;
        }

        // 2. Sucesso de transação (messageType)
        if (isset($postData['messageType']) && in_array($postData['messageType'], self::SUCCESS_MESSAGE_TYPES, true)) {
            $finger = $this->fingerprintResponse($postData);
            $fingerValid = ($postData['resultFingerPrint'] ?? '') === $finger;

            $result['status'] = $fingerValid ? 'SUCCESS' : 'INVALID_FINGERPRINT';
            $result['message'] = $fingerValid ? 'Transação válida.' : 'Fingerprint inválido (verificar segurança).';
            $result['success'] = true;

            if (!$fingerValid) {
                $result['debug'] = [
                    'recebido' => $postData['resultFingerPrint'] ?? '',
                    'calculado' => $finger
                ];
            }

            // 3. DCC (apenas compras / transactionCode = 1)
            if (($postData['transactionCode'] ?? '') === self::TRANSACTION_TYPE_PURCHASE && !empty($postData['merchantRespDCCData'])) {
                $dcc = json_decode($postData['merchantRespDCCData'], true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($dcc)) {
                    $result['dcc'] = [
                        'enabled' => ($dcc['dcc'] ?? 'N') === 'Y',
                        'amount' => $dcc['dccAmount'] ?? null,
                        'currency' => $dcc['dccCurrency'] ?? null,
                        'markup' => $dcc['dccMarkup'] ?? null,
                        'rate' => $dcc['dccRate'] ?? null
                    ];
                } else {
                    $result['dcc'] = [
                        'enabled' => false,
                        'error' => 'DCC inválido ou mal formatado'
                    ];
                }
            }

            return $result;
        }

        // 4. Erro na transação
        $result['message'] = $postData['merchantRespErrorDescription'] ?? 'Transação falhou.';
        $result['detail'] = $postData['merchantRespErrorDetail'] ?? null;

        return $result;
    }


    // ------------------------------------------------------------------
    // Privados
    // ------------------------------------------------------------------

    /**
     * Método interno responsável por montar o payload e fingerprint.
     */
    private function preparePaymentRequest(array $params): void
    {
        if ($this->prepared) {
            throw new Exception("Vinti4Net: ONLY 1 PAYMENT REQUEST MUST BE PREPARED");
        }

        $this->request = [
            'posID' => $this->posID,
            'merchantRef' => $params['merchantRef'] ?? 'R' . date('YmdHis'),
            'merchantSession' => $params['merchantSession'] ?? 'S' . date('YmdHis'),
            'amount' => (int)(float)$params['amount'],
            'currency' => $this->currencyToCode($params['currency'] ?? self::CURRENCY_CVE),
            'transactionCode' => $params['transactionCode'] ?? self::TRANSACTION_TYPE_PURCHASE,
            'languageMessages' => $params['languageMessages'] ?? 'pt',
            'entityCode' => $params['entityCode'] ?? '',
            'referenceNumber' => $params['referenceNumber'] ?? '',
            'timeStamp' => date('Y-m-d H:i:s'),
            'fingerprintversion' => '1',
            'is3DSec' => '1',
            'urlMerchantResponse' => $params['urlMerchantResponse'] ?? '',
            'billing' => $params['billing'] ?? []
        ];

        $this->prepared = true;
    }

    /**
     * Método interno responsável por gerar fingerprint, purchaseRequest e URL final.
     */
    private function processRequest(array $fields): array
    {
        // PurchaseRequest (3DS)
        if ($fields['transactionCode'] === self::TRANSACTION_TYPE_PURCHASE && !empty($fields['billing'])) {
            $fields = array_merge($fields, $fields['billing']);
            $fields['purchaseRequest'] = $this->generatePurchaseRequest($fields['billing']);
        }
        
        $type = $fields['transactionCode'] != self::TRANSACTION_TYPE_REFUND ? 'payment' : 'refund';
        
        // Fingerprint
        unset($fields['billing']);
        $fields['fingerprint'] = $this->fingerprintRequest($fields, $type);

        // URL final de envio
        $postUrl = $this->baseUrl . '?' . http_build_query([
            'FingerPrint' => $fields['fingerprint'],
            'TimeStamp' => $fields['timeStamp'],
            'FingerPrintVersion' => $fields['fingerprintversion']
        ]);

        return [
            'postUrl' => $postUrl,
            'fields' => $fields
        ];
    }

    /**
     * Normaliza dados de billing e user no formato completo do SISP (3DS).
     * Suporta 'user' embutido dentro do array de billing.
     */
    private function normalizeBilling(array $billing): array
    {
        $user = $billing['user'] ?? [];
        unset($billing['user']);

        // Helpers
        $get = fn($src, $key, $default = null) =>
        is_array($src) ? ($src[$key] ?? $default) : ($src->$key ?? $default);

        $extractCC = fn(string $phone, string $default = '238'): string =>
        preg_match('/^(?:\+|00)?(\d{1,3})/', preg_replace('/\D+/', '', $phone), $m)
            ? $m[1] : $default;

        // 🧩 Campos básicos
        $email    = $billing['email'] ?? $get($user, 'email');
        $country  = $billing['billAddrCountry'] ?? $get($user, 'country', '132');
        $city     = $billing['billAddrCity']  ?? $get($user, 'city', '');
        $line1    = $billing['billAddrLine1'] ?? $get($user, 'address', '');
        $line2    = $billing['billAddrLine2'] ?? $get($user, 'address2', '');
        $line3    = $billing['billAddrLine3'] ?? $get($user, 'address3', '');
        $postcode = $billing['billAddrPostCode'] ?? $get($user, 'postCode', '');
        $state    = $billing['billAddrState'] ?? $get($user, 'state');

        // 🧩 Telefones
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

        // 🧩 Dados de conta (3DS)
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

        // 🧩 Montagem final
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

    private function currencyToCode(string $currency): int
    {
        return match (strtoupper($currency)) {
            'CVE' => 132, // Cape Verdean Escudo
            'USD' => 840, // US Dollar
            'EUR' => 978, // Euro
            'BRL' => 986, // Brazilian Real
            'GBP' => 826, // British Pound
            'JPY' => 392, // Japanese Yen
            'AUD' => 36,  // Australian Dollar
            'CAD' => 124, // Canadian Dollar
            'CHF' => 756, // Swiss Franc
            'CNY' => 156, // Chinese Yuan
            'INR' => 356, // Indian Rupee
            'ZAR' => 710, // South African Rand
            'RUB' => 643, // Russian Ruble
            'MXN' => 484, // Mexican Peso
            'KRW' => 410, // South Korean Won
            'SGD' => 702,  // Singapore Dollar
            default => is_numeric($currency) ? (int)$currency : throw new InvalidArgumentException("Invalid currency code: $currency")
        };
    }

    /**
     * Gera fingerprint de envio.
     */
    private function fingerprintRequest(array $data, string $type = 'payment'): string
    {
        $encodedPOSAuthCode = base64_encode(hash('sha512', $this->posAuthCode, true));

        if ($type === 'payment') {
            // Pagamentos / Serviços / Recharge
            $amount = (float)($data['amount'] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $entity = !empty($data['entityCode']) ? (int)$data['entityCode'] : '';
            $reference = !empty($data['referenceNumber']) ? (int)$data['referenceNumber'] : '';

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
        } else {
            // Refund
            $amount = (float)($data['amount'] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash = $encodedPOSAuthCode .
                ($data['transactionCode'] ?? '') .
                ($data['posID'] ?? '') .
                ($data['merchantRef'] ?? '') .
                ($data['merchantSession'] ?? '') .
                $amountLong .
                ($data['currency'] ?? '') .
                ($data['clearingPeriod'] ?? '') .
                ($data['transactionID'] ?? '') .
                ($data['reversal'] ?? '') .
                ($data['urlMerchantResponse'] ?? '') .
                ($data['languageMessages'] ?? '') .
                ($data['fingerPrintVersion'] ?? '') .
                ($data['timeStamp'] ?? '');
        }

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Gera fingerprint de resposta.
     */
    private function fingerprintResponse(array $data, string $type = 'payment'): string
    {
        $posAuthCode = $this->posAuthCode;

        // 🔐 Chave base
        $encodedPOSAuthCode = base64_encode(hash('sha512', $posAuthCode, true));

        if ($type === 'payment') {
            // Campos de pagamento / serviço / recharge
            $amount = (float)($data["merchantRespPurchaseAmount"] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash =
                $encodedPOSAuthCode .
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
        } else {
            // Campos de refund
            $amount = (float)($data["merchantRespPurchaseAmount"] ?? 0);
            $amountLong = (int) bcmul($amount, '1000', 0);

            $toHash =
                $encodedPOSAuthCode .
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
        }

        return base64_encode(hash('sha512', $toHash, true));
    }

    /**
     * Gera campo purchaseRequest codificado em Base64.
     */
    private function generatePurchaseRequest(array $billing): string
    {
        $required = ['billAddrCountry', 'billAddrCity', 'billAddrLine1', 'billAddrPostCode', 'email'];

        $billing = $this->normalizeBilling($billing);

        foreach ($required as $key) {
            if (empty($billing[$key])) {
                throw new InvalidArgumentException("Campo obrigatório ausente em billing: {$key}");
            }
        }

        $json = json_encode($billing, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new InvalidArgumentException("Erro ao gerar JSON de billing.");
        }

        return base64_encode($json);
    }

}
