# Fingerprint

O fingerprint protege a integridade dos dados trocados com a SISP. A ordem dos campos é fixa e não deve ser alterada.

## Pedido

A biblioteca concatena, nesta ordem:

1. hash SHA-512 do `posAuthCode`, codificado em Base64;
2. `timeStamp`;
3. montante convertido para a representação do fingerprint;
4. `merchantRef`;
5. `merchantSession`;
6. `posID`;
7. `currency`;
8. `transactionCode`;
9. `entityCode`;
10. `referenceNumber`.

O resultado recebe SHA-512 binário e Base64. `createPaymentForm()` faz tudo automaticamente.

## Resposta bem-sucedida

Para uma resposta de sucesso, a biblioteca calcula o valor esperado com os campos definidos pela SISP e compara usando `hash_equals()`.

Os tipos tratados como sucesso são:

```text
8, 10, P, M
```

Uma resposta desses tipos só vira `SUCCESS` quando o fingerprint recebido existe e é válido. Compra (`8`) também exige `merchantResp=C`; estorno (`10`) não exige esse campo.

## Cancelamento e erro

O cálculo de resposta bem-sucedida não é aplicado a payloads de cancelamento ou erro. Eles são classificados como `CANCELLED` ou `ERROR`, nunca como sucesso.

Nesses casos, `fingerprint_valid=true` significa apenas que **não foi aplicada a fórmula de sucesso**; não é prova de autenticidade do payload de erro. Nunca marque uma operação como paga ou estornada com base num erro ou cancelamento. Confirme o estado financeiro pela referência guardada e pelo fluxo de conciliação aplicável.

Isso é importante porque uma recusa como “Unable validate secure password” pode trazer `resultFingerPrint`, mas não usa necessariamente o mesmo conjunto de campos de uma resposta aprovada.

## Se a validação falhar

```php
if ($response->hasInvalidFingerprint()) {
    error_log('Vinti4Net: fingerprint inválido.');
    http_response_code(400);
    exit('Resposta inválida.');
}
```

Confira:

- se o `posAuthCode` é exatamente o fornecido pela SISP;
- se está usando as credenciais do mesmo ambiente do endpoint;
- se o POST chegou sem alterações;
- se não houve conversão de tipos ou limpeza dos campos antes de `processResponse()`;
- se a aplicação está usando o código final da v2.2.

!!! danger
    Não ignore um fingerprint inválido e não tente “corrigir” a ordem dos campos no projeto consumidor.

