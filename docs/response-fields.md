# Campos recebidos

O callback da SISP pode variar conforme o resultado e o tipo da operação. Nem todos os campos aparecem em todas as respostas.

## Identificação

| Campo SISP | Uso na biblioteca |
| --- | --- |
| `messageType` | Identifica o tipo de resposta |
| `merchantRespMerchantRef` | `getMerchantRef()` |
| `merchantRespMerchantSession` | Sessão enviada pelo comerciante |
| `merchantRespTid` | `getTransactionId()` |
| `merchantRespCP` | `getClearingPeriod()` |
| `merchantRespMessageID` | Identificador/código de autorização retornado |

## Valores

| Campo SISP | Uso |
| --- | --- |
| `merchantRespPurchaseAmount` | `getAmount()`; pode ser `0` num estorno aprovado |
| `merchantRespCurrency` | `getCurrency()` |
| `merchantRespEntityCode` | Entidade de serviço/recarga |
| `merchantRespReferenceNumber` | Referência do serviço/recarga |
| `merchantRespReloadCode` | Código de recarga, quando existir |

## Estado e erros

| Campo SISP | Uso |
| --- | --- |
| `UserCancelled` | Indica cancelamento pelo utilizador |
| `merchantResp` | Resultado da operação |
| `merchantRespErrorCode` | Código do erro |
| `merchantRespErrorDescription` | Descrição principal do erro |
| `merchantRespErrorDetail` | Detalhe adicional |
| `merchantRespAdditionalErrorMessage` | Mensagem complementar |

A primeira mensagem descritiva não vazia fica disponível em `$response->message`.

A prioridade real é `merchantRespAdditionalErrorMessage`, depois `merchantRespErrorDetail` e, por último, `merchantRespErrorDescription`.

## Segurança e recibo

| Campo SISP | Uso |
| --- | --- |
| `resultFingerPrint` | Fingerprint recebido |
| `resultFingerPrintVersion` | Versão do fingerprint |
| `merchantRespTimeStamp` | Data/hora da resposta |
| `merchantRespPan` | Cartão; mascarado em `toArray()` e nos recibos |
| `merchantRespClientReceipt` | Conteúdo de recibo devolvido |
| `merchantRespDCCData` | JSON com dados DCC |

## Acesso ao payload

```php
$raw = $response->data;
$safe = $response->toArray();
$json = $response->toJson();
```

`data` contém o payload original. Prefira `toArray()` ou `toJson()` para logs, porque eles mascaram o PAN.

