# Referência e sessão

Cada pagamento precisa de uma referência e uma sessão do comerciante.

```php
$vinti4->setMerchant(
    reference: 'PEDIDO000000001',
    session: 'S20260906133152',
);
```

## `merchantRef`

É o identificador da operação no seu sistema. O validador da biblioteca aceita até **15 caracteres**, mas no ambiente SISP a referência pode ser recusada se não tiver **exatamente 15**; use sempre 15 e não reutilize a referência de outra operação.

Boas referências:

```text
PEDIDO000000001
FAT202600001234
RECARGA00000042
```

Guarde a referência no banco antes de enviar o cliente à página da Vinti4. No callback, compare `merchantRespMerchantRef` com essa referência.

O SDK pode gerar `R` + `date('YmdHis')` por omissão, e também expõe `Vinti4Net::generateMerchantRef()`. Isso produz 15 caracteres, mas **não garante unicidade** quando duas operações são criadas no mesmo segundo. Para pagamentos reais, gere e persista uma referência única na sua aplicação.

## `merchantSession`

Identifica a tentativa de pagamento. O validador da v2.2 exige **exatamente 15 caracteres**.

Se a sessão não for informada, `setMerchant()` gera:

```php
'S' . date('YmdHis')
```

Exemplo:

```text
S20260906133152
```

Essa sessão tem exatamente 15 caracteres. Ela não depende de `session_id()` do PHP.

```php
$vinti4->setMerchant('PEDIDO000000001');
```

## Nova tentativa

Cada tentativa deve ter uma sessão distinta e corresponder ao registo salvo pela aplicação. A geração automática com `date('YmdHis')` não garante unicidade quando há mais de uma tentativa no mesmo segundo. Confirme as regras de reutilização de referência aplicáveis ao seu POS antes de repetir uma operação.

!!! warning
    A referência e a sessão não provam que o pagamento foi aprovado. Sempre processe o callback, valide o estado e compare os dados com o pedido guardado.

