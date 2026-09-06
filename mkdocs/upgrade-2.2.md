# Atualização da v2.1 para v2.2

```bash
composer require erilshk/vinti4net:^2.2
```

A v2.2 mantém o fluxo público da linha v2 e concentra as melhorias em normalização, segurança, respostas e recibos.

## Principais mudanças

- `Billing::make()` e `Billing::from()` fornecem uma construção mais explícita.
- A biblioteca usa `Vinti4Exception` para suas falhas.
- `Vinti4Response` distingue sucesso, cancelamento, erro e fingerprint inválido.
- Erros descritivos da SISP ficam disponíveis em `message` e `detail`.
- `renderReceipt()` unifica recibo padrão e template personalizado.
- `renderDccReceipt()` gera o recibo DCC com os valores originais da SISP.
- `toArray()` e `toJson()` mascaram o PAN.

## APIs deprecated

| API anterior | Preferir na v2.2 |
| --- | --- |
| `Billing::create()` | `Billing::from(...)->toArray()` |
| `addrMatch()` | `addressMatchesShipping()` |
| `acctID()` | `accountId()` |
| `acctInfo()` | `accountInfo()` |
| `fromUser()` | mapeamento explícito com `Billing::from()` |

Esses métodos continuam disponíveis em v2.2 para reduzir quebras. Não há necessidade de migrar para o namespace planejado da v3 nesta release.

## Checklist

1. Atualize a dependência e execute os testes.
2. Teste compra, cancelamento, código incorreto e callback aprovado no ambiente SISP.
3. Confirme a persistência de referência, sessão, transaction ID e clearing period.
4. Verifique os templates de recibo e DCC.
5. Rode `composer test` e a análise estática antes da tag.
