# 🏗️ SDK Architecture Overview

O diagrama abaixo apresenta **as classes principais do SDK** e suas dependências:

```mermaid
classDiagram
    class Vinti4Net {
        - Payment payment
        - Refund refund
        - array request
        - bool prepared
        + setRequestParams()
        + setMerchant()
        + preparePurchasePayment()
        + prepareServicePayment()
        + prepareRechargePayment()
        + prepareRefundPayment()
        + createPaymentForm()
        + processResponse()
        + getRequest()
    }

    class Payment {
        +preparePayment()
        +processResponse()
    }

    class Refund {
        +preparePayment()
        +processResponse()
    }

    class Billing {
        +make()
        +create()
        +fill()
        +toArray()
    }

    class Vinti4Response {
        +status
        +message
        +success
        +data
        +dcc
        +debug
        +detail
        +isSuccess()
        +isCancelled()
        +hasInvalidFingerprint()
        +getTransactionId()
        +getMerchantRef()
        +getAmount()
        +getCurrency()
        +generateReceiptHtml()
        +toArray()
        +toJson()
    }

    Vinti4Net --> Payment
    Vinti4Net --> Refund
    Vinti4Net --> Billing
    Vinti4Net --> Vinti4Response
```

---

# 🔹 Flow of a Payment

O fluxo completo de **uma transação** no SDK:

```mermaid
sequenceDiagram
    participant Merchant
    participant SDK as Vinti4Net SDK
    participant SISP
    participant User

    Merchant->>SDK: preparePurchasePayment() / prepareServicePayment() / prepareRechargePayment()
    Merchant->>SDK: createPaymentForm()
    SDK->>Merchant: HTML Auto-submit Form

    Merchant->>User: Redirect to Payment Page
    User->>SISP: Complete Payment
    SISP-->>Merchant: POST Callback

    Merchant->>SDK: processResponse()
    SDK-->>Merchant: Vinti4Response
```

---

### 🔹 Observações

1. `Vinti4Net` é a **fachada principal**; todas as operações passam por ela.  
2. `Payment` e `Refund` cuidam da **lógica de criação e processamento** das transações.  
3. `Billing` simplifica a preparação dos campos **3DS**.  
4. `Vinti4Response` encapsula todas as respostas do SISP, padronizando **status, mensagens e dados adicionais**.  
5. O **sequence diagram** mostra o fluxo típico: do `preparePayment` até o retorno de `Vinti4Response`.  

---


# 📌 Fluxo Oficial do Processamento das Transações (SISP / MPI)

```mermaid
sequenceDiagram
    autonumber

    participant C as Cliente
    participant M as Comerciante<br/>(Seu Sistema)
    participant SDK as Vinti4Net SDK
    participant S as SISP<br/>MPI / 3DSServer

    C->>M: a. Faz checkout do produto/serviço
    M->>SDK: b. Adiciona dados do pagamento + dados do comerciante
    SDK->>S: b. Envia POST (request) para o URL SISP
    S->>M: c. Autentica comerciante<br/>c.1 Em caso de falha → devolve erro
    S->>C: d. Página SISP para recolha dos dados do cartão
    C->>S: e. Autentica transação (3DS)
    S->>S: f. Processamento da autorização
    S->>M: g. Envia POST de resposta (response)<br/>indica sucesso ou falha
```

