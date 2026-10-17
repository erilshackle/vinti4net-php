# Tokenização (v2.5)

Com a tokenização, o cliente autoriza a utilização do cartão na página da SISP.
Depois da adesão, a sua aplicação recebe um token para associar ao cliente e
usar em pagamentos futuros, sem voltar a recolher os dados do cartão.

Esta página mostra o fluxo que a aplicação precisa implementar. O SDK prepara
o pedido e valida a resposta; a aplicação continua responsável por guardar os
pedidos, associar tokens ao cliente, registar o consentimento e controlar o
estado das operações.

## Fluxo de integração

1. O cliente autenticado escolhe uma operação na sua aplicação.
2. O servidor prepara a operação e cria uma referência única.
3. A aplicação guarda o pedido pendente e o contexto necessário para reconhecer
   o callback.
4. A aplicação apresenta o formulário devolvido por `createPaymentForm()`, que
   envia o cliente para a SISP.
5. No callback, a aplicação encontra o pedido guardado, valida a assinatura e
   confere a referência e a sessão antes de alterar qualquer estado.
6. Só após uma adesão validada a aplicação guarda o token, associado ao cliente
   e ao consentimento registado.

Não confie em montantes, tokens ou códigos de operação enviados pelo browser
para decidir o que cobrar. Obtenha esses valores no servidor e no registo
persistido da operação.

## Operações disponíveis

| Operação | Método | Código | Dados necessários |
| --- | --- | --- | --- |
| Adesão / criação do token | `prepareTokenRequest($minimumAmount)` | `5` | Montante mínimo para pagamentos futuros |
| Compra com token | `prepareTokenPurchase($token, $amount)` | `6` | Token guardado e montante |
| Pagamento de serviço | `prepareTokenServicePayment($token, $amount, $entity, $number)` | `2` | Token, montante, entidade e referência |
| Recarga | `prepareTokenRecharge($token, $amount, $entity, $number)` | `3` | Token, montante, entidade e número |
| Cancelamento de token | `prepareTokenCancellation($token)` | `7` | Token a cancelar; o montante é zero |

Os montantes são valores inteiros positivos em CVE (`132`); o cancelamento usa
zero. No enrollment, o montante é o mínimo autorizado para pagamentos futuros,
não um débito feito durante a adesão. Serviço e recarga exigem entidade
numérica de até 5 dígitos e referência/número de até 9 dígitos.

Use tokens como strings opacas. Preserve zeros iniciais e não os converta para
números. Cada método `prepareToken*()` substitui a operação anteriormente
preparada: crie um formulário separado para cada operação.

## Preparar um pedido

Crie o cliente no servidor com as credenciais guardadas em variáveis de
ambiente. Não envie `posAuthCode` ao browser.

```php
use Erilshk\Sisp\Vinti4Net;

$client = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);

$reference = Vinti4Net::generateMerchantRef();
$client
    ->prepareTokenRequest('100')
    ->setMerchant($reference);

$request = $client->getRequest();
$form = $client->createPaymentForm(
    $_ENV['VINTI4_TOKEN_CALLBACK_URL'],
    'pt',
);

// Grave antes de apresentar o formulário: a referência e o código esperado
// serão usados para localizar e validar o callback.
$pendingRequests->save([
    'merchantRef' => $reference,
    'merchantSession' => $request['merchantSession'],
    'transactionCode' => '5',
    'customerId' => $authenticatedCustomerId,
    'status' => 'pending',
]);

echo $form;
```

`$pendingRequests` representa a camada de persistência da sua aplicação. Guarde
no mínimo a referência, a sessão, o cliente, o código da operação, os dados
necessários para conferir o resultado e o estado pendente. Use uma restrição
única para a referência. Gere uma nova referência e sessão para cada tentativa.

Para operações com um token já guardado, obtenha-o do registo do cliente
autenticado no servidor; não aceite um token arbitrário enviado pelo browser:

```php
$reference = Vinti4Net::generateMerchantRef();
$client
    ->prepareTokenPurchase($tokenGuardadoDoCliente, '500')
    ->setMerchant($reference);

$request = $client->getRequest();
$form = $client->createPaymentForm($callbackUrl, 'pt');

$pendingRequests->save([
    'merchantRef' => $reference,
    'merchantSession' => $request['merchantSession'],
    'transactionCode' => '6',
    'customerId' => $authenticatedCustomerId,
    'amount' => '500',
    'status' => 'pending',
]);

echo $form;
```

Substitua o método de preparação conforme a operação:

```php
$client->prepareTokenServicePayment($token, '500', 6, '227045251');
$client->prepareTokenRecharge($token, '500', 2, '9573234');
$client->prepareTokenCancellation($token);
```

Cada exemplo acima é uma operação independente: defina uma nova referência,
guarde o respetivo código (`2`, `3` ou `7`) e gere o formulário depois de a
preparar.

## Receber e validar o callback

No callback, não confie no código de operação enviado no POST. Recupere-o do
pedido pendente e passe-o a `processTokenResponse()`. O método valida a
assinatura e a versão do fingerprint; nos callbacks assinados A/B/C, também
confere o tipo com a operação esperada. Respostas de erro (`messageType=6`)
seguem a validação de erro própria e não identificam por si só qual operação
originou o callback. A aplicação deve ainda conferir a referência e a sessão
com os valores guardados.

O exemplo seguinte mostra a sequência do controller. `$pendingRequests`
representa a camada de persistência da aplicação; os métodos `complete...Once`
devem atualizar o estado de forma atómica e idempotente, numa transação da
base de dados.

```php
use Erilshk\Sisp\Exceptions\Vinti4Exception;
use Erilshk\Sisp\Vinti4Net;

$reference = (string) (
    $_POST['merchantRespMerchantRef']
    ?? $_POST['merchantRef']
    ?? ''
);

if ($reference === '') {
    http_response_code(400);
    exit('Referência ausente.');
}

$pending = $pendingRequests->findByReference($reference);
if ($pending === null) {
    http_response_code(404);
    exit('Pedido pendente não encontrado.');
}

$client = new Vinti4Net(
    posID: $_ENV['VINTI4_POS_ID'],
    posAuthCode: $_ENV['VINTI4_AUTH_CODE'],
);

try {
    $response = $client->processTokenResponse(
        $_POST,
        (string) $pending['transactionCode'],
    );
} catch (Vinti4Exception $exception) {
    error_log('Callback de token inválido: ' . $exception->getMessage());
    http_response_code(400);
    exit('Não foi possível processar a resposta.');
}

$callbackSession = (string) (
    $response->data['merchantRespMerchantSession']
    ?? $response->data['merchantSession']
    ?? ''
);

if (
    $callbackSession === ''
    || !hash_equals((string) $pending['merchantSession'], $callbackSession)
) {
    http_response_code(400);
    exit('A sessão não corresponde ao pedido.');
}

if ($response->hasInvalidFingerprint()) {
    http_response_code(400);
    exit('Não foi possível validar a resposta.');
}

if ($response->isCancelled()) {
    $pendingRequests->completeCancelledOnce($pending['id']);
    exit('Operação interrompida pelo cliente.');
}

if ($response->hasFailed()) {
    $pendingRequests->completeFailedOnce($pending['id']);
    exit('Operação não aprovada.');
}

if (!$response->isSuccess()) {
    http_response_code(400);
    exit('Estado de resposta inesperado.');
}

if ($pending['transactionCode'] === '5') {
    $pendingRequests->completeEnrollmentOnce(
        $pending['id'],
        $pending['customerId'],
        $response->getToken(),
        $response->getTokenDescription(),
        $response->getTokenLimits(),
    );
} else {
    // Confira também montante, cliente e tipo de operação contra o pedido guardado.
    $pendingRequests->completeApprovedOperationOnce($pending['id'], $response);
}

http_response_code(200);
echo 'Resposta processada.';
```

O exemplo usa nomes ilustrativos para as operações da camada de persistência;
implemente-as com as suas tabelas e transações. A conclusão “Once” deve alterar
somente pedidos ainda pendentes, para que callbacks repetidos não criem tokens
duplicados nem confirmem duas vezes a mesma operação. Antes de aprovar uma
compra, confira também o montante e os dados da operação com o pedido guardado.

`isCancelled()` representa uma interrupção pelo cliente, sem aprovação da
operação. Um callback C válido de cancelamento de token, por outro lado,
representa uma operação concluída e resulta em `isSuccess()`. Verifique sempre
`hasInvalidFingerprint()` antes de atualizar o pedido ou guardar dados do token.
Os acessores de resposta não substituem essa validação.

Uma interrupção sem `messageType` pode ser reconhecida pelo indicador de
cancelamento do cliente e não tem a assinatura A/B/C. Trate-a apenas como uma
operação interrompida; nunca como aprovação ou autorização para guardar token.

Os callbacks assinados usam o tipo `A` para enrollment, `B` para pagamentos e
`C` para cancelamento. A fachada `Vinti4Net` espera `C` no cancelamento. Se a
SISP lhe indicar um tipo diferente, confirme o fluxo antes de o integrar; a
alternativa `B` está disponível apenas na integração avançada com `Core\Token`.

## Guardar e utilizar tokens

- Associe o token ao cliente autenticado e ao registo do consentimento obtido
  pela sua aplicação. A biblioteca não gere consentimento, utilizadores ou
  armazenamento.
- Trate o token como dado sensível: restrinja o acesso, proteja-o em repouso e
  não o inclua em páginas, URLs, logs ou mensagens de erro.
- Em pagamentos futuros, carregue o token do cliente no servidor e confirme
  que o montante, a entidade e a frequência respeitam o consentimento e os
  limites devolvidos no enrollment.
- O SDK não agenda cobranças recorrentes nem envia notificações. A aplicação
  implementa agendamento, prevenção de duplicados, reconciliação e cancelamento.
- Após um cancelamento aprovado, não volte a usar o token em novas operações.

`getTokenLimits()` devolve os campos de limite disponibilizados no enrollment:
montante máximo, número máximo de transações e data limite. A interpretação e
aplicação desses limites à sua política de cobrança são responsabilidade da
aplicação, de acordo com o contrato SISP.

## Endpoints e callback

Sem configuração adicional, a biblioteca utiliza estas rotas:

| Operação | Endpoint padrão |
| --- | --- |
| Enrollment | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenEnrollment` |
| Pagamento | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenPayment` |
| Cancelamento | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenCancel` |

Confirme com a SISP os endpoints e o ambiente aplicáveis às suas credenciais.
Se lhe for fornecido outro destino, o argumento `endpoint` existente no
construtor aceita uma URL completa:

```php
$client = new Vinti4Net($posId, $posAuthCode, $endpointCompleto);
```

Não é uma URL base: o SDK não acrescenta o caminho da operação. O mesmo valor
é utilizado para as operações do cliente; se a integração exigir URLs
diferentes, crie o cliente com o URL apropriado para cada pedido. O endpoint
deve usar HTTPS. O URL de callback é outro parâmetro: é o destino no seu
servidor passado a `createPaymentForm()`.

Em produção, publique o callback em HTTPS acessível pela SISP. HTTP em
`localhost`, `127.0.0.1` e `::1` é uma exceção do SDK para desenvolvimento
local; não torna o serviço local acessível à SISP. Não dependa de sessão de
navegador para encontrar o pedido: um POST de retorno pode não incluir o
cookie. Use uma base de dados ou outro armazenamento do lado do servidor.

O projeto inclui um [exemplo interativo](https://github.com/erilshackle/vinti4net-php/blob/main/examples/token_example.php)
e um [exemplo de callback](https://github.com/erilshackle/vinti4net-php/blob/main/examples/token_response.php).
Estes exemplos usam sessão PHP para demonstração; substitua-a por persistência
durável antes de utilizar em produção.

## Testes e entrada em produção

Os testes automatizados do projeto utilizam vetores sintéticos próprios. São
úteis para detetar regressões, mas não comprovam homologação nem substituem
testes com as credenciais e os procedimentos fornecidos pela SISP. Antes de
ativar a funcionalidade em produção, confirme com a SISP o ambiente, os
endpoints, os tipos de callback, os limites e o processo de homologação.

Para detalhes de instalação e disponibilidade por versão, consulte
[Instalação](installation.md) e as [notas de versão](release-notes.md). Para
as assinaturas públicas da fachada e os métodos de resposta, consulte a
[referência da API](api.md); para práticas gerais de validação, consulte
[Segurança](security.md).
