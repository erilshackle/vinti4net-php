# Perguntas frequentes

## Preciso informar os dados 3DS numa compra?

Não. Passe `[]` como segundo argumento: `$vinti4->preparePurchase(1500, [])`. Se fornecer billing, preencha email, cidade, morada e código postal. O país usa `132` por padrão.

## Como escolho a referência?

Use `setMerchant($reference)` antes de preparar a transação. Escolha uma referência de **15 caracteres** e guarde-a no pedido. A referência gerada automaticamente usa a data e a hora, pelo que duas transações no mesmo segundo podem receber o mesmo valor.

## Por que aparece “Nenhum pagamento preparado”?

Chame primeiro `preparePurchase()`, `prepareServicePayment()`, `prepareRecharge()` ou `prepareRefund()`; depois chame `createPaymentForm()`.

## Que valor devo passar em `amount`?

Um inteiro positivo, por exemplo `1500`. O SDK não aceita montantes fracionários neste fluxo. Para um estorno, passe o montante **total da transação original**.

## O callback diz “Fingerprint Invalid” ou mostra um erro da SISP. O que faço?

Se `hasInvalidFingerprint()` for verdadeiro, não confirme a operação: verifique credenciais, ambiente e integridade do POST recebido. Se `hasFailed()` for verdadeiro, consulte `$response->message` e `$response->detail`. Um erro de estorno pode chegar com `messageType=6`; associe a referência ao pedido guardado para saber qual era a operação.

## Posso tratar um cancelamento como pagamento falhado?

Use `$response->isCancelled()` para distinguir a decisão do cliente de uma recusa ou erro. Nenhum dos dois confirma o pagamento.

## Por que o valor no callback de estorno é zero?

A SISP pode devolver `merchantRespPurchaseAmount=0` mesmo num estorno aprovado. Recupere o valor original no seu sistema e passe-o a `renderRefundReceipt()`.

## Por que o recibo DCC não aparece?

Confirme que `$response->isDccEnabled()` é `true` e que o retorno contém montante, moeda, taxa e câmbio. Sem esses dados, `renderDccReceipt()` lança `Vinti4Exception`.
