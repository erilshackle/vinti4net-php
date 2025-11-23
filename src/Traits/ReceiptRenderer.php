<?php

namespace Erilshk\Sisp\Traits;

use Erilshk\Sisp\Core\Sisp;

/**
 * Trait para renderização de recibos HTML
 */
trait ReceiptRenderer
{
    private bool $renderWithStyle = true;

    /**
     * Gera um recibo HTML básico baseado nos dados da transação
     */
    public function generateReceiptHtml(?string $companyName = null, bool $text = false): string
    {
        $data = $this->data;
        $transactionCode = $data['transactionCode'] ?? $data['messageType'] ?? '';
        $this->renderWithStyle = !$text;
        
        return match($transactionCode) {
            '8', Sisp::TRANSACTION_TYPE_PURCHASE => $this->renderPurchaseReceipt($companyName),
            'P', Sisp::TRANSACTION_TYPE_SERVICE => $this->renderServiceReceipt($companyName),
            'M', Sisp::TRANSACTION_TYPE_RECHARGE => $this->renderRechargeReceipt($companyName),
            '10', Sisp::TRANSACTION_TYPE_REFUND => $this->renderRefundReceipt($companyName),
            default => $this->renderGenericReceipt($companyName)
        };
    }

    /**
     * Recibo para compras (3DS)
     */
    private function renderPurchaseReceipt(?string $companyName = null): string
    {
        $data = $this->data;
        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        
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
                    <div class=\"amount\">{$this->formatCurrency($amount, $currency)}</div>
                    <div class=\"description\">Pagamento de serviços</div>
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
        $data = $this->data;
        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        
        $entityCode = $data['merchantRespEntityCode'] ?? $data['entityCode'] ?? '';
        $reference = $data['merchantRespReferenceNumber'] ?? $data['referenceNumber'] ?? '';
        
        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE PAGAMENTO</h2>
                <div class=\"merchant\">{$this->escape($this->getEntityName($entityCode) ?? $companyName ?? 'Entidade de Serviços')}</div>
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
                    <div class=\"amount\">{$this->formatCurrency($amount, $currency)}</div>
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
        $data = $this->data;
        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        
        $entityCode = $data['merchantRespEntityCode'] ?? $data['entityCode'] ?? '';
        $reference = $data['merchantRespReferenceNumber'] ?? $data['referenceNumber'] ?? '';
        
        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE RECARGA</h2>
                <div class=\"merchant\">{$this->escape($this->getEntityName($entityCode) ?? $companyName ?? 'Operadora')}</div>
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
                    <div class=\"amount\">{$this->formatCurrency($amount, $currency)}</div>
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
        $data = $this->data;
        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        
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
                    <div class=\"amount\">-{$this->formatCurrency($amount, $currency)}</div>
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

    /**
     * Recibo genérico para casos não especificados
     */
    private function renderGenericReceipt(?string $companyName = null): string
    {
        $data = $this->data;
        $amount = $this->getAmount();
        $currency = $this->getCurrency();
        
        return "
        <div class=\"vinti4-receipt\">
            <div class=\"receipt-header\">
                <h2>COMPROVATIVO DE TRANSAÇÃO</h2>
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
                    <div class=\"amount\">{$this->formatCurrency($amount, $currency)}</div>
                    <div class=\"description\">{$this->getAditionalMessage()}</div>
                    </div>
                </div>
                    
                    <div class=\"receipt-footer\">
                    <div class=\"status {$this->getStatusClass()}\">{$this->getStatusIcon()} {$this->getStatusText()}</div>
                <div class=\"timestamp\">Emitido em {$this->getCurrentTimestamp()}</div>
            </div>
        </div>
        
        <style>{$this->getReceiptStyles()}</style>";
    }

    // =========================================================================
    // MÉTODOS AUXILIARES
    // =========================================================================

    private function renderDccSection(): string
    {
        if (empty($this->dcc) || !($this->dcc['enabled'] ?? false)) {
            return '';
        }

        $dcc = $this->dcc;
        return "
        <div class=\"dcc-info\">
            <div class=\"dcc-notice\">Pagamento em moeda estrangeira</div>
            <div class=\"row\">
                <span class=\"label\">Taxa de câmbio:</span>
                <span class=\"value\">1 {$dcc['currency']} = {$dcc['rate']} CVE</span>
            </div>
            <div class=\"row\">
                <span class=\"label\">Valor original:</span>
                <span class=\"value\">{$this->formatCurrency($dcc['amount'], $dcc['currency'])}</span>
            </div>
            <div class=\"row\">
                <span class=\"label\">Margem DCC:</span>
                <span class=\"value\">{$dcc['markup']}%</span>
            </div>
        </div>";
    }

    private function getStatusClass(): string
    {
        return match($this->status) {
            'SUCCESS' => 'success',
            'CANCELLED' => 'cancelled',
            'INVALID_FINGERPRINT' => 'error',
            default => 'error'
        };
    }

    private function getStatusIcon(): string
    {
        return match($this->status) {
            'SUCCESS' => '✓',
            'CANCELLED' => '⏹',
            'INVALID_FINGERPRINT' => '⚠',
            default => '✗'
        };
    }

    private function getStatusText(): string
    {
        return match($this->status) {
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
        $currencySymbol = match($currency) {
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
        return $this->data['merchantRespAdditionalErrorMessage'] ?? '';
    }

    private function maskPan(?string $pan): string
    {
        if (empty($pan) || strlen($pan) < 8) return '•••• •••• •••• ••••';
        
        $firstSix = substr($pan, 0, 6);
        $lastFour = substr($pan, -4);
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
        return match($entityCode) {
            '10001' => 'ELECTRA',
            '10002' => 'ÁGUAS DE CABO VERDE',
            '10021' => 'CVMÓVEL',
            '10022' => 'UNITEL T+',
            default => 'Entidade'
        };
    }

    private function getEntityContact(?string $entityCode): string
    {
        return match($entityCode) {
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
        }" : ".vinti4-receipt {font-family: courier, monospace;}";
    }
}

?>
