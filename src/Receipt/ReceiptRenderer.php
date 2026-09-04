<?php

namespace Eril\Sisp\Receipt;

use Eril\Sisp\Vinti4Response;

final class ReceiptRenderer
{
    private bool $renderWithStyle = true;

    public function __construct(
        private readonly Vinti4Response $response
    ) {}

    public function html(?string $companyName = null, bool $styled = true): string
    {
        $this->renderWithStyle = $styled;

        if (!$this->response->isSuccess()) {
            return $this->renderUnavailableReceipt(
                "Transação não concluída com sucesso.",
                $this->response->GetAdditionalErrorMessage()
            );
        }

        $data = $this->response->data;
        $transactionCode = $data['messageType'] ?? '';

        return match ($transactionCode) {
            '8'  => $this->renderPurchaseReceipt($companyName),
            'P'  => $this->renderServiceReceipt($companyName),
            'M'  => $this->renderRechargeReceipt($companyName),
            '10' => $this->renderRefundReceipt($companyName),
            default => $this->renderUnavailableReceipt(
                "Recibo indisponível para este tipo de transação."
            )
        };
    }

    public function text(?string $companyName = null): string
    {
        $data = $this->response->data;
        $company = $companyName ?? 'Comerciante/Entidade';

        $text = "==== RECIBO DE TRANSAÇÃO ====\n";
        $text .= "Empresa: {$company}\n";
        $text .= "Data/Hora: " . ($data['merchantRespTimeStamp'] ?? date('d/m/Y H:i:s')) . "\n";
        $text .= "Status: " . ($this->response->isSuccess() ? 'APROVADA' : 'NÃO CONCLUÍDA') . "\n";
        $text .= "Mensagem: " . ($this->response->message ?? 'N/A') . "\n\n";

        $text .= "Transação ID: " . ($data['merchantRespTid'] ?? 'N/A') . "\n";
        $text .= "Referência: " . ($data['merchantRespMerchantRef'] ?? 'N/A') . "\n";

        $amount = $this->response->getAmount();
        $currency = $this->response->getCurrency();

        if ($amount !== null) {
            $text .= "Valor: " . number_format($amount, 2, ',', '.') . " {$currency}\n";
        }

        return $text . "\n===========================\n";
    }

    // AQUI você copia todos os métodos privados
    // renderPurchaseReceipt()
    // renderServiceReceipt()
    // renderRechargeReceipt()
    // renderRefundReceipt()
    // helpers (maskPan, formatCurrency, etc.)

      /**
     * Renderiza uma notificação de recibo indisponível
     */
    private function renderUnavailableReceipt(string $message, string $detail = ''): string
    {
        return "
    <div class=\"vinti4-receipt unavailable\">
        <div class=\"receipt-header\">
            <h2>RECIBO INDISPONÍVEL</h2>
        </div>
        <div class=\"receipt-body\">
            <p>{$this->escape($message)}</p>
            <p style=\"\">{$this->escape($detail)}</p>
        </div>
        <div class=\"receipt-footer\">
            <div class=\"status error\">⚠ TRANSAÇÃO NÃO CONCLUÍDA</div>
            <div class=\"timestamp\">Emitido em {$this->getCurrentTimestamp()}</div>
        </div>
    </div>
    <style>
        .vinti4-receipt.unavailable { 
            font-family: Arial, sans-serif; 
            max-width: 400px; 
            margin: 20px auto; 
            border: 2px solid #f5c6cb; 
            border-radius: 8px; 
            padding: 20px; 
            background: #f8d7da; 
            color: #721c24;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-header h2 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        .receipt-footer {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            text-align: center;
        }
        .status.error { 
            font-weight: bold; 
            padding: 8px 12px; 
            border-radius: 4px; 
            background: #f5c6cb; 
            color: #721c24; 
            display: inline-block; 
        }
    </style>";
    }



    /**
     * Recibo para compras (3DS)
     */
    private function renderPurchaseReceipt(?string $companyName = null): string
    {
        $data = $this->response->data;
        $amount = $this->response->getAmount();
        $currency = $this->response->getCurrency();

        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE PAGAMENTO</h2>
                <div class=\"merchant\">{$this->escape($companyName ?? 'Comerciante')}</div>
            </div>
            
            <div class=\"receipt-body\">
                <div class=\"transaction-info\">
                    <div class=\"row\">
                        <span class=\"label\">Referência:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespMerchantRef'] ?? 'N/A')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Data/Hora:</span>
                        <span class=\"value\">{$this->formatTimestamp($data['merchantRespTimeStamp'] ?? '')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Transação ID:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespTid'] ?? 'N/A')}</span>
                    </div>
                </div>
                
                <div class=\"amount-section\">
                    <div class=\"amount\">{$this->formatCurrency($amount,$currency)}</div>
                    <div class=\"description\">Compra</div>
                </div>
                
                <div class=\"card-info\">
                    <div class=\"row\">
                        <span class=\"label\">Cartão:</span>
                        <span class=\"value\">{$this->maskPan($data['merchantRespPan'] ?? '')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Autorização:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespMessageID'] ?? 'N/A')}</span>
                    </div>
                </div>
                
                {$this->renderDccSection()}
            </div>
            
            <div class=\"receipt-footer\">
                <div class=\"status {$this->getStatusClass()}\">{$this->getStatusIcon()} {$this->getStatusText()}</div>
                <div class=\"timestamp\">Emitido em {$this->getCurrentTimestamp()}</div>
            </div>
        </div>
        
        <style>{$this->getReceiptStyles()}</style>";
    }

    /**
     * Recibo para serviços (água, luz, etc.)
     */
    private function renderServiceReceipt(?string $companyName = null): string
    {
        $data = $this->response->data;
        $amount = $this->response->getAmount();
        $currency = $this->response->getCurrency();

        $entityCode = $data['merchantRespEntityCode'] ?? $data['entityCode'] ?? '';
        $reference = $data['merchantRespReferenceNumber'] ?? $data['referenceNumber'] ?? '';

        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE PAGAMENTO</h2>
                <div class=\"merchant\">{$this->escape($this->getEntityName($entityCode) ??$companyName ?? 'Entidade de Serviços')}</div>
            </div>
            
            <div class=\"receipt-body\">
                <div class=\"transaction-info\">
                    <div class=\"row\">
                        <span class=\"label\">Entidade:</span>
                        <span class=\"value\">{$entityCode} ({$this->getEntityName($entityCode)})</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Referência:</span>
                        <span class=\"value\">{$this->escape($reference)}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Data:</span>
                        <span class=\"value\">{$this->formatTimestamp($data['merchantRespTimeStamp'] ?? '')}</span>
                    </div>
                </div>
                
                <div class=\"amount-section\">
                    <div class=\"amount\">{$this->formatCurrency($amount,$currency)}</div>
                    <div class=\"description\">Pagamento de serviço</div>
                </div>
                
                <div class=\"payment-info\">
                    <div class=\"row\">
                        <span class=\"label\">Transação:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespTid'] ?? 'N/A')}</span>
                    </div>
                </div>
            </div>
            
            <div class=\"receipt-footer\">
                <div class=\"status {$this->getStatusClass()}\">{$this->getStatusIcon()} {$this->getStatusText()}</div>
                <div class=\"contact\">{$this->getEntityContact($entityCode)}</div>
            </div>
        </div>
        
        <style>{$this->getReceiptStyles()}</style>";
    }

    /**
     * Recibo para recargas
     */
    private function renderRechargeReceipt(?string $companyName = null): string
    {
        $data = $this->response->data;
        $amount = $this->response->getAmount();
        $currency = $this->response->getCurrency();

        $entityCode = $data['merchantRespEntityCode'] ?? $data['entityCode'] ?? '';
        $reference = $data['merchantRespReferenceNumber'] ?? $data['referenceNumber'] ?? '';

        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE RECARGA</h2>
                <div class=\"merchant\">{$this->escape($this->getEntityName($entityCode) ??$companyName ?? 'Operadora')}</div>
            </div>
            
            <div class=\"receipt-body\">
                <div class=\"transaction-info\">
                    <div class=\"row\">
                        <span class=\"label\">Número:</span>
                        <span class=\"value\">{$this->formatPhoneNumber($reference)}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Data/Hora:</span>
                        <span class=\"value\">{$this->formatTimestamp($data['merchantRespTimeStamp'] ?? '')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Transação:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespTid'] ?? 'N/A')}</span>
                    </div>
                </div>
                
                <div class=\"amount-section\">
                    <div class=\"amount\">{$this->formatCurrency($amount,$currency)}</div>
                    <div class=\"description\">Recarga de telemóvel</div>
                </div>
                
                <div class=\"recharge-info\">
                    <div class=\"row\">
                        <span class=\"label\">Código:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespReloadCode'] ?? 'N/A')}</span>
                    </div>
                </div>
            </div>
            
            <div class=\"receipt-footer\">
                <div class=\"status {$this->getStatusClass()}\">{$this->getStatusIcon()} {$this->getStatusText()}</div>
                <div class=\"note\">A recarga foi creditada com sucesso</div>
            </div>
        </div>
        
        <style>{$this->getReceiptStyles()}</style>";
    }

    /**
     * Recibo para reembolsos
     */
    private function renderRefundReceipt(?string $companyName = null): string
    {
        $data = $this->response->data;
        $amount = $this->response->getAmount();
        $currency = $this->response->getCurrency();
        $formatedAmount = '-' . trim($this->formatCurrency($amount,$currency), '-');

        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE REEMBOLSO</h2>
                <div class=\"merchant\">{$this->escape($companyName ?? 'Comerciante')}</div>
            </div>
            
            <div class=\"receipt-body\">
                <div class=\"transaction-info\">
                    <div class=\"row\">
                        <span class=\"label\">Referência original:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespMerchantRef'] ?? 'N/A')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Transação original:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespTransactionID'] ?? 'N/A')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Data reembolso:</span>
                        <span class=\"value\">{$this->formatTimestamp($data['merchantRespTimeStamp'] ?? '')}</span>
                    </div>
                </div>
                
                <div class=\"amount-section refund\">
                    <div class=\"amount\">{$formatedAmount}</div>
                    <div class=\"description\">Reembolso de pagamento</div>
                </div>
                
                <div class=\"card-info\">
                    <div class=\"row\">
                        <span class=\"label\">Cartão creditado:</span>
                        <span class=\"value\">{$this->maskPan($data['merchantRespPan'] ?? '')}</span>
                    </div>
                    <div class=\"row\">
                        <span class=\"label\">Período liquidação:</span>
                        <span class=\"value\">{$this->escape($data['merchantRespClearingPeriod'] ?? 'N/A')}</span>
                    </div>
                </div>
            </div>
            
            <div class=\"receipt-footer\">
                <div class=\"status {$this->getStatusClass()}\">{$this->getStatusIcon()} {$this->getStatusText()}</div>
                <div class=\"note\">O valor será creditado em 2-3 dias úteis</div>
            </div>
        </div>
        
        <style>{$this->getReceiptStyles()}</style>";
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    private function renderDccSection(): string
    {
        if (empty($this->response->dcc) || !($this->response->dcc['enabled'] ?? false)) {
            return '';
        }

        $dcc = $this->response->dcc;
        return "
        <div class=\"dcc-info\">
            <div class=\"dcc-notice\">Pagamento em moeda estrangeira</div>
            <div class=\"row\">
                <span class=\"label\">Taxa de câmbio:</span>
                <span class=\"value\">1 {$dcc['currency']} = {$dcc['rate']} CVE</span>
            </div>
            <div class=\"row\">
                <span class=\"label\">Valor original:</span>
                <span class=\"value\">{$this->formatCurrency($dcc['amount'],$dcc['currency'])}</span>
            </div>
            <div class=\"row\">
                <span class=\"label\">Margem DCC:</span>
                <span class=\"value\">{$dcc['markup']}%</span>
            </div>
        </div>";
    }

    private function getStatusClass(): string
    {
        return match ($this->response->status) {
            'SUCCESS' => 'success',
            'CANCELLED' => 'cancelled',
            'INVALID_FINGERPRINT' => 'error',
            default => 'error'
        };
    }

    private function getStatusIcon(): string
    {
        return match ($this->response->status) {
            'SUCCESS' => '✓',
            'CANCELLED' => '⏹',
            'INVALID_FINGERPRINT' => '⚠',
            default => '✗'
        };
    }
    private function getTransactionTypeText($t, $pt = true): string
    {
        return match ($t) {
             '8', => $pt ? "Compra" : 'Purchase',
            'P', => $pt ? "Pagamento de Serviço" : 'Service Payment',
            'M', => $pt ? "Recarga" : 'Recharge',
            '10', => $pt ? "Estorno" : 'Refund',
            default => 'N/A'
        };
    }

    private function getStatusText(): string
    {
        return match ($this->response->status) {
            'SUCCESS' => 'TRANSAÇÃO APROVADA',
            'CANCELLED' => 'TRANSAÇÃO CANCELADA',
            'INVALID_FINGERPRINT' => 'ERRO DE SEGURANÇA',
            default => 'TRANSAÇÃO RECUSADA'
        };
    }

    private function formatCurrency(?float $amount, ?string $currency): string
    {
        if ($amount === null) return 'N/A';

        $formatted = number_format($amount, 2, ',', ' ');
        $currencySymbol = match ($currency) {
            'USD' => 'USD',
            'EUR' => 'EUR',
            '132', 'CVE' => 'CVE',
            default => $currency ?? 'CVE'
        };

        return "{$formatted} {$currencySymbol}";
    }

    private function formatTimestamp(?string $timestamp): string
    {
        if (empty($timestamp)) return 'N/A';

        try {
            $date = new \DateTime($timestamp);
            return $date->format('d/m/Y H:i:s');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function getCurrentTimestamp(): string
    {
        return date('d/m/Y H:i:s');
    }

    private function getAditionalMessage(): string
    {
        return $this->response->data['merchantRespAdditionalErrorMessage'] ?? '';
    }

    private function maskPan(?string $pan): string
    {
        if (empty($pan) || strlen($pan) < 8) return '•••• •••• •••• ••••';

        $firstSix = substr($pan, 0, 6);
        $lastFour = substr($pan, -4);
        if($firstSix === '******') {
            $firstSix = '••••••';
        }
        return "{$firstSix}••••{$lastFour}";
    }

    private function formatPhoneNumber(?string $phone): string
    {
        if (empty($phone)) return 'N/A';

        $clean = preg_replace('/\D/', '', $phone);
        if (strlen($clean) === 7) {
            return '+238 ' . substr($clean, 0, 3) . ' ' . substr($clean, 3, 2) . ' ' . substr($clean, 5, 2);
        }

        return $phone;
    }

    private function getEntityName(?string $entityCode): string
    {
        return match ($entityCode) {
            '10001' => 'ELECTRA',
            '10002' => 'ÁGUAS DE CABO VERDE',
            '10021' => 'CVMÓVEL',
            '10022' => 'UNITEL T+',
            default => 'Entidade'
        };
    }

    private function getEntityContact(?string $entityCode): string
    {
        return match ($entityCode) {
            '10001' => 'Contacto: 262 30 60',
            '10002' => 'Contacto: 800 20 20',
            '10021' => 'Contacto: 111',
            '10022' => 'Contacto: 101',
            default => ''
        };
    }

    private function escape(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }

    private function getReceiptStyles(): string
    {

        return $this->renderWithStyle ? "
        .vinti4-receipt {
            font-family: 'Arial', sans-serif;
            max-width: 400px;
            margin: 20px auto;
            border: 2px solid #333;
            border-radius: 8px;
            padding: 20px;
            background: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px solid #333;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .receipt-header h2 {
            margin: 0 0 10px 0;
            color: #333;
            font-size: 18px;
            font-weight: bold;
        }
        .merchant {
            font-weight: bold;
            color: #666;
        }
        .receipt-body {
            margin-bottom: 20px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding: 4px 0;
        }
        .label {
            font-weight: bold;
            color: #666;
        }
        .value {
            color: #333;
        }
        .amount-section {
            text-align: center;
            margin: 25px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 4px solid #007bff;
        }
        .amount-section.refund {
            border-left-color: #dc3545;
        }
        .amount {
            font-size: 24px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .description {
            color: #666;
            font-style: italic;
        }
        .dcc-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 12px;
            margin: 15px 0;
        }
        .dcc-notice {
            font-weight: bold;
            color: #856404;
            margin-bottom: 8px;
        }
        .receipt-footer {
            border-top: 1px solid #ddd;
            padding-top: 15px;
            text-align: center;
        }
        .status {
            font-weight: bold;
            padding: 8px 12px;
            border-radius: 4px;
            margin-bottom: 10px;
            display: inline-block;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .status.cancelled {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .timestamp, .contact, .note {
            color: #666;
            font-size: 12px;
            margin-top: 5px;
        }" : "
        .vinti4-receipt {font-family: courier, monospace;}
        .amount {font-weight: bolder; padding: 0.5em;}
        .receipt-footer {border-top: 1px solid #ddd; padding-top: 15px; text-align: center;}
        .label {font-weight: bold; color: #666;}
        .value {color: #333;}
        ";
    }
}