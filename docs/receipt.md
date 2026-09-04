# API – Recibos HTML (Trait `ReceiptRenderer`)

O **`ReceiptRenderer`** é um **trait** utilizado na classe `Vinti4Response` para gerar **recibos HTML** de transações processadas pelo SISP. Ele suporta compras, serviços, recargas e reembolsos, além de casos genéricos.

---

## Principais métodos

| Método                                                                 | Parâmetros                                                                     | Retorno  | Descrição                                                                       |
| ---------------------------------------------------------------------- | ------------------------------------------------------------------------------ | -------- | ------------------------------------------------------------------------------- |
| `generateReceiptHtml(?string $companyName = null, bool $text = false)` | `$companyName` – Nome da empresa<br>`$text` – Gera recibo sem estilo se `true` | `string` | Gera o HTML completo do recibo, escolhendo automaticamente o tipo de transação. |
| `renderPurchaseReceipt(?string $companyName = null)`                   | `$companyName`                                                                 | `string` | Recibo de compra 3DS.                                                           |
| `renderServiceReceipt(?string $companyName = null)`                    | `$companyName`                                                                 | `string` | Recibo para serviços públicos (água, luz, etc.).                                |
| `renderRechargeReceipt(?string $companyName = null)`                   | `$companyName`                                                                 | `string` | Recibo para recargas de telemóvel.                                              |
| `renderRefundReceipt(?string $companyName = null)`                     | `$companyName`                                                                 | `string` | Recibo para reembolsos.                                                         |
| `renderGenericReceipt(?string $companyName = null)`                    | `$companyName`                                                                 | `string` | Recibo para casos genéricos ou tipos não especificados.                         |

---

### Recursos e opções

* Suporte a **DCC** (Dynamic Currency Conversion) em compras.
* Máscara de cartão (`PAN`) para segurança.
* Formatação automática de **moeda** e **timestamp**.
* Diferentes cores e ícones para status:

  * ✅ `SUCCESS` – Transação aprovada
  * ⏹ `CANCELLED` – Transação cancelada
  * ⚠ `INVALID_FINGERPRINT` – Erro de segurança
  * ❌ Outros erros
* Estilo CSS interno ou texto puro (via `$text = true`).

---

### Exemplo de uso

```php
$response = $vinti->processResponse($_POST);

// Gera recibo com estilo padrão
echo $response->generateReceiptHtml("Minha Empresa");

// Gera recibo texto puro (sem CSS)
echo $response->generateReceiptHtml("Minha Empresa", true);
```

---

### Exemplo de output – Sucesso (compra 3DS)

<div class="vinti4-receipt">
  <div class="receipt-header">
    <h2>COMPROVATIVO DE PAGAMENTO</h2>
    <div class="merchant">Minha Empresa</div>
  </div>
  <div class="receipt-body">
    <div class="transaction-info">
      <div class="row"><span class="label">Referência:</span><span class="value">REF12345</span></div>
      <div class="row"><span class="label">Data/Hora:</span><span class="value">30/11/2025 14:32:10</span></div>
      <div class="row"><span class="label">Transação ID:</span><span class="value">TID987654321</span></div>
    </div>
    <div class="amount-section">
      <div class="amount">100,50 CVE</div>
      <div class="description">Pagamento de serviços</div>
    </div>
    <div class="card-info">
      <div class="row"><span class="label">Cartão:</span><span class="value">123456••••7890</span></div>
      <div class="row"><span class="label">Autorização:</span><span class="value">AUTH001</span></div>
    </div>
  </div>
  <div class="receipt-footer">
    <div class="status success">✓ TRANSAÇÃO APROVADA</div>
    <div class="timestamp">Emitido em 30/11/2025 14:32:12</div>
  </div>
</div>

---

### Exemplo de output – Erro (Recibo Indisponível)


<div class="vinti4-receipt-error unavailable">
        <div class="receipt-header">
            <h2>RECIBO INDISPONÍVEL</h2>
        </div>
        <div class="receipt-body">
            <p>Sistema Temporariamente Indisponível</p>
            <p></p>
        </div>
        <div class="receipt-footer">
            <div class="status-error error">⚠ TRANSAÇÃO NÃO CONCLUÍDA</div>
            <div class="timestamp">Emitido em 30/11/2025 14:32:12</div>
        </div>
    </div>

<style>
        .vinti4-receipt-error.unavailable { 
            font-family: Arial, sans-serif; 
            text-align: center;
            max-width: 400px; 
            margin: 20px auto; 
            border: 2px solid #f5c6cb; 
            border-radius: 8px; 
            padding: 20px; 
            background: #f8d7da; 
            color: #721c24;
        }
        .status-error.error { 
            font-weight: bold; 
            padding: 8px 12px; 
            border-radius: 4px; 
            background: #f5c6cb; 
            color: #721c24; 
            display: inline-block; 
        }
</style>


### Exemplo Output - Recibo Sem estilo (texto puro)

<style>
    body {
        font-family: monospace;
    }
    pre {
        /* background: #fff; */
        border: 1px solid #333;
        border-radius: 6px;
        padding: 20px;
        max-width: 600px;
        white-space: pre-wrap;
        word-wrap: break-word;
    }
</style>

<h2>Transação de Sucesso</h2>
<pre>
==== RECIBO DE TRANSAÇÃO ====
Empresa: Loja ABC
Data/Hora: 30/11/2025 15:42:10
Status: APROVADA
Mensagem: Transação válida.

Transação ID: 123456789
Referência: REF-20251130-001
Tipo de Transação: 8
Valor: 10.000,00 CVE
Cartão: 123456••••7890
Autorização: AUTH-987654

===========================
</pre>

<h2>Transação com Erro</h2>
<pre>
==== RECIBO DE TRANSAÇÃO ====
Empresa: Loja ABC
Data/Hora: 30/11/2025 15:45:30
Status: NÃO CONCLUÍDA
Mensagem: Erro de processamento.

Transação ID: N/A
Referência: REF-20251130-002
Tipo de Transação: 8
Valor: 5.000,00 CVE
Cartão: 123456••••1234

=== DETALHES DE ERRO ===
Erro: Saldo insuficiente
Mensagem adicional: Falha ao processar pagamento

===========================
</pre>

---


<style>
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
        }" : ".vinti4-receipt {font-family: courier, monospace;}
</style>