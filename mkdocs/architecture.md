# Arquitetura

`Vinti4Net` é a fachada pública. Ela prepara o estado da transação e delega ao processador correto:

- `Core\\Payment`: compra, serviço e recarga.
- `Core\\Refund`: reembolso.
- `Core\\Sisp`: validações comuns, fingerprint e processamento da resposta.
- `Billing`: normalização dos dados 3DS.
- `Vinti4Response`: interpretação segura do callback.
- `Receipt\\Receipt`: recibos padrão, personalizados e DCC.

Aplicações devem depender principalmente de `Vinti4Net`, `Billing`, `Vinti4Response` e `Vinti4Exception`. As classes de `Core` são detalhes internos sensíveis ao contrato da SISP.

O fingerprint depende da ordem e da representação exata dos campos. Não modifique essa lógica ao personalizar formulários, callbacks ou recibos.

