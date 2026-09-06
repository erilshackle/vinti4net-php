# Segurança

## Antes de enviar o pagamento

1. Gere a referência no servidor.
2. Leia o montante do seu banco de dados, não do formulário do cliente.
3. Guarde referência, sessão, montante, moeda e estado `pendente`.
4. Só então gere o formulário Vinti4Net.

## Ao receber o callback

1. Passe o POST original para `processResponse()`.
2. Interrompa se `hasInvalidFingerprint()` for verdadeiro.
3. Trate cancelamento e erro sem confirmar o pedido.
4. Em sucesso, localize o pedido pela referência.
5. Compare sessão, montante e moeda.
6. Confirme uma única vez, dentro de uma transação no banco.
7. Guarde `transactionId` e `clearingPeriod`.

## Idempotência

O mesmo callback pode chegar ou ser processado mais de uma vez. Atualize o pedido somente se ele ainda estiver pendente.

```php
if ($payment->status === 'paid') {
    exit('OK');
}

// Atualize para pago e grave o transaction ID atomicamente.
```

## Dados sensíveis

- Nunca salve o `posAuthCode` no código-fonte.
- Não envie credenciais ao navegador.
- Não registre o payload bruto sem filtrar.
- Não armazene o PAN completo.
- Use HTTPS no callback.
- Não confie em parâmetros enviados pelo cliente para definir o valor.

## Redirecionamento não é confirmação

O cliente voltar ao seu site não significa que pagou. A confirmação vem da resposta processada e das verificações feitas no servidor.

