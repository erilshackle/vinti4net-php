# Perguntas frequentes

## Integração e billing

### O SDK substitui a adesão à SISP?

Não. O comerciante precisa das credenciais e das condições de integração
fornecidas pela SISP. O SDK é um projeto independente que implementa o protocolo;
não fornece um POS nem recolhe os dados do cartão.

### Preciso informar billing numa compra?

`preparePurchase()` exige o segundo argumento, mas aceita um array vazio.
Isso omite `purchaseRequest`; **não desativa 3DS**, pois `is3DSec` permanece `1`.
Para o fluxo 3DSServer com cartões internacionais, forneça os dados exigidos
pela SISP para a sua integração.

::: tip Ao fornecer billing
Informe email válido, cidade e morada. O país tem padrão `132`; o código postal
de cobrança desconhecido recebe `0000`. Contactos e histórico da conta devem
corresponder ao cliente, não a valores inventados.
:::

### País deve ser `132` ou `CPV`? E a subdivisão?

Use o código numérico ISO 3166-1 indicado pela SISP, como `'132'` para Cabo Verde,
e a subdivisão ISO 3166-2, como `'PR'` para Praia. A biblioteca preserva os valores
recebidos, sem converter `CPV` em `132`; aceitar uma string no helper não garante
que o gateway a aceite.

### Como informo o telefone?

Separe o indicativo do número local:

```php
$billing->mobilePhone(cc: '238', subscriber: '9911122');
```

Uma string local em `Billing::from()` usa o indicativo `238`. Não passe
`+2389911122` como número local, pois o prefixo internacional não é interpretado.
Sem telefone de trabalho, pode informar o contacto pessoal em `workPhone()`.

### Qual é a diferença entre email, `acctID` e `acctInfo`?

O email identifica o contacto/conta do cliente. `acctID` é o ID do utilizador no
sistema do comerciante e também pode receber o email de acesso, respeitando o
limite de 64 caracteres. `acctInfo` contém o histórico da conta: criação,
alteração do perfil, alteração da senha e indicadores definidos pelo comerciante.

`Billing::account()` permite fornecer esses dados com parâmetros nomeados.
Use datas `YYYYMMDD`; `changedAt` refere-se ao perfil e `passwordChangedAt`
à senha. Indicadores omitidos não são calculados automaticamente.

## Preparar a operação

### Como escolho a referência e a sessão?

Ambas devem ter exatamente **15 caracteres**. Pode gerar a referência e deixar
a biblioteca gerar a sessão:

```php
$reference = Vinti4Net::generateMerchantRef();
$vinti4->setMerchant($reference);
$session = $vinti4->getRequest()['merchantSession'];
```

O formato padrão da referência é `R` + `ymdHis` + dois caracteres aleatórios;
`generateMerchantRef(random: true)` gera uma referência inteiramente aleatória.
Guarde referência e sessão por tentativa e garanta a unicidade no seu sistema.

### Que valor devo passar em `amount`?

Um montante inteiro positivo, como `'1500'`, com até 13 dígitos. Não use vírgula,
separador de milhares ou valor fracionário. Para um estorno, use o **total da
transação original**, recuperado do pedido guardado.

### Posso usar qualquer moeda ou entidade das constantes?

`Currency` fornece códigos ISO e `Entity` fornece códigos de serviço/recarga.
As constantes facilitam a configuração; não habilitam moedas nem serviços
no POS. Confirme os códigos aplicáveis com a SISP ou a entidade.

### Por que aparece “Nenhum pagamento preparado”?

Chame um método `preparePurchase()`, `prepareServicePayment()`,
`prepareRecharge()` ou `prepareRefund()` antes de `createPaymentForm()`.
Use uma operação por envio; preparar outra substitui os dados da anterior.

## Retorno e recibos

### O que fazer com fingerprint inválido ou erro da SISP?

Verifique primeiro `hasInvalidFingerprint()`. Se for verdadeiro, rejeite a
resposta e confira credenciais, ambiente e integridade do POST. `hasFailed()`
identifica o estado `ERROR`; consulte `message` e `detail` para o motivo.

::: warning Antes de confirmar
Mesmo numa resposta de sucesso, associe referência, sessão e montante ao pedido
guardado e confirme apenas uma vez. A resposta não devolve a moeda original:
use a moeda persistida pela aplicação.
:::

### Cancelamento é o mesmo que erro?

Não. `isCancelled()` identifica a decisão do cliente. Esse retorno contém
`merchantRef`, `merchantSession` e `UserCancelled`, sem fingerprint.
Um erro de processamento usa `messageType=6` e tem assinatura própria.
Nenhum desses estados confirma um pagamento.

### Por que o montante do estorno pode ser zero?

A resposta pode trazer `merchantRespPurchaseAmount=0` mesmo num estorno aprovado.
Use o montante original guardado ao chamar `renderRefundReceipt()`.
Erros com `messageType=6` não identificam, por si só, se a operação era compra
ou estorno; consulte a tentativa guardada pela referência.

### Por que o recibo DCC não aparece?

Confira `isDccEnabled()` e a presença de montante, moeda, markup e taxa de
conversão. Sem os dados necessários, `renderDccReceipt()` lança
`Vinti4Exception`. `dcc['currency']` é a moeda da conversão, não a moeda original
do pedido; `dccMarkup` é um montante, não uma percentagem.

<details>
<summary>Onde encontro exemplos completos?</summary>

Consulte [Billing](billing.md), [integração completa](payment-integration-guide.md),
[respostas](responses.md) e [recibo DCC](dcc.md).

</details>
