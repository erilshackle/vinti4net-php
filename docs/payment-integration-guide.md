# Integração completa

## 1. Criar o pedido no seu sistema

Antes de chamar a biblioteca, guarde referência única, sessão, montante, moeda, cliente e estado `pendente`.

```php
use Erilshk\Sisp\Vinti4Net;

$vinti4 = new Vinti4Net($posID, $authCode);
$reference = Vinti4Net::generateMerchantRef();
$vinti4->setMerchant($reference);
$session = $vinti4->getRequest()['merchantSession'];
$amount = '1500';
$currency = '132';

// Guarde estes valores na tentativa de pagamento antes de enviar o formulário.
```

A referência padrão combina data e sufixo aleatório. Para uma referência
inteiramente aleatória, use `Vinti4Net::generateMerchantRef(random: true)`.
A sessão gerada e a referência têm exatamente 15 caracteres.

## 2. Preparar a transação

```php

$vinti4
    ->setMerchant($reference, $session)
    ->preparePurchase($amount, $billing, $currency);
```

Se não quiser enviar dados de billing, use `preparePurchase($amount, [])`.

Use um método `prepare...()` por envio. Outro `prepare...()` substitui os dados específicos da transação anterior.

## 3. Enviar para a Vinti4

```php
echo $vinti4->createPaymentForm(
    responseUrl: 'https://loja.example.cv/pagamentos/vinti4/callback',
    lang: 'pt',
);
```

O método retorna o formulário que encaminha o cliente para a Vinti4.

## 4. Receber e classificar o callback

```php
$response = $vinti4->processResponse($_POST);

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Resposta inválida.');
}

if ($response->isCancelled()) {
    exit('Operação cancelada.');
}

if ($response->hasFailed()) {
    http_response_code(422);
    exit($response->message);
}
```

## 5. Validar contra o seu banco

| Dado | Callback | Seu backend (db) |
| --- | --- | --- |
| Referência | `getMerchantRef()` | referência do pedido |
| Sessão | `$response->data['merchantRespMerchantSession']` | sessão da tentativa |
| Montante | `getAmount()` | total esperado |
| Estado | `isSuccess()` | ainda pendente |

Só confirme a compra quando tudo estiver correto. A resposta não devolve a moeda original: use a moeda guardada no pedido. `getCurrency()` está depreciado; a moeda DCC, quando presente, é consultada separadamente.

Para estornos, não compare `getAmount()` com o valor original: o callback aprovado pode trazer `0`. Recupere o montante do estorno guardado na aplicação. Um erro `messageType=6` também não identifica por si só se veio de pagamento ou estorno.

## 6. Guardar dados para reembolso

```php
$transactionId = $response->getTransactionId();
$clearingPeriod = $response->getClearingPeriod();
```

## 7. Mostrar o recibo

```php
echo $response->isDccEnabled()
    ? $response->renderDccReceipt()
    : $response->renderReceipt(data: [
        'companyName' => 'Minha Empresa, Lda.',
    ]);
```

| Estado | Ação recomendada |
| --- | --- |
| `SUCCESS` | Conferir os dados e confirmar uma vez |
| `ERROR` | Mostrar mensagem segura e permitir nova tentativa |
| `CANCELLED` | Não confirmar; permitir voltar ao pedido |
| `INVALID_FINGERPRINT` | Rejeitar e investigar |
