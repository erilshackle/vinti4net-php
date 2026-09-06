# Referência e sessão

Cada pagamento precisa de uma referência e uma sessão do comerciante.

```php
$vinti4->setMerchant(
    reference: 'PEDIDO00000001',
    session: 'S20260906133152',
);
```

## `merchantRef`

É o identificador da operação no seu sistema. Deve ser único e ter no máximo **15 caracteres**.

Boas referências:

```text
PEDIDO00000001
FAT20260001234
RECARGA0000042
```

Guarde a referência no banco antes de enviar o cliente à página da Vinti4. No callback, compare `merchantRespMerchantRef` com essa referência.

## `merchantSession`

Identifica a tentativa de pagamento. Também tem no máximo **15 caracteres**.

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
$vinti4->setMerchant('PEDIDO00000001');
```

## Nova tentativa

Uma nova tentativa pode manter a referência do pedido, mas deve usar outra sessão. Isso ajuda a diferenciar duas idas à página de pagamento.

!!! warning
    A referência e a sessão não provam que o pagamento foi aprovado. Sempre processe o callback, valide o estado e compare os dados com o pedido guardado.

