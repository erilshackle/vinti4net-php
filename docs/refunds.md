# 💸 Refunds

Para **reembolsar** uma transação existente, use o método:

```php
$vinti4->prepareRefundPayment(
    amount: 1000,                  // 💰 Valor a reembolsar
    merchantRef: "PED123",         // 🏷️ Referência original do merchant
    merchantSession: "sess-444",   // 🔑 Sessão do merchant
    transactionID: "TX119922",     // 🆔 ID da transação SISP
    clearingPeriod: "D+1"          // 📅 Período de compensação obrigatório
);
```

> ⚠️ **Nota:** O parâmetro `clearingPeriod` é obrigatório para reembolsos e deve seguir o formato definido pelo SISP (ex.: `D+1`, `D+2`).

---

## 🔹 Fluxo de Reembolso

```mermaid
graph LR
    style A fill:#fef3c7,stroke:#78350f,stroke-width:2px
    style B fill:#ede9fe,stroke:#6b21a8,stroke-width:2px
    style C fill:#d1fae5,stroke:#065f46,stroke-width:2px
    style D fill:#fff7ed,stroke:#c2410c,stroke-width:2px
    style E fill:#fef2f2,stroke:#b91c1c,stroke-width:2px
    style F fill:#dbeafe,stroke:#1e40af,stroke-width:2px

    A["Merchant"] --> B["prepareRefundPayment()"]
    B --> C["createPaymentForm()"]
    C --> D["SISP"]
    D --> E["Callback"]
    E --> F["processResponse()"]
```