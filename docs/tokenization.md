# Tokenização SISP

A API Composer oferece enrollment (adesão), compra, serviço, recarga e cancelamento através de `Core\Token`, com fingerprints independentes dos pagamentos tradicionais. O titular introduz os dados do cartão e autentica o enrollment na SISP. A aplicação guarda o token devolvido, associado ao cliente e ao consentimento.

## Configuração

```php
use Erilshk\Sisp\Vinti4Net;

$client = new Vinti4Net(
    posID: $posId,
    posAuthCode: $posAuthCode,
);
```

O construtor original é `new Vinti4Net($posID, $posAuthCode, $endpoint = null)`. Os endpoints padrão de tokenização seguem o site de testes SISP:

| Operação | URL |
| --- | --- |
| Enrollment | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenEnrollment` |
| Pagamento | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenPayment` |
| Cancelamento | `https://mc.vinti4net.cv/BizMPIOnUsSisp/TokenCancel` |

Os caminhos padrão estão centralizados em `Token::ENDPOINT_PATHS`. O terceiro argumento existente do construtor (`endpoint`) é uma URL completa personalizada e agora também é encaminhado ao processador Token, seguindo o padrão de Payment/Refund. Se for omitido, cada operação usa a sua rota padrão. Não há novos argumentos nem métodos de configuração na fachada. Na API direta `Core\Token`, o terceiro argumento também é `?string $endpoint`; `cancelMessageType` continua a isolar a alternativa B/C.

Os destinos de token exigem HTTPS sem fragmento ao gerar o pedido. Query strings existentes são preservadas. O callback aceita HTTPS ou HTTP estritamente em localhost, 127.0.0.1 e ::1, sem credenciais nem fragmento, para desenvolvimento local.

O arquivo `examples/token_example.php`, acessível a partir do índice dos exemplos, demonstra as cinco operações. Configure `VINTI4_POS_ID`, `VINTI4_AUTH_CODE` e `VINTI4_TOKEN_CALLBACK_URL` com um callback HTTPS acessível pela SISP. O exemplo usa sessão para correlacionar os pedidos; uma aplicação deve persistir essa informação na base de dados. Em HTTP local, o cookie usa SameSite=Lax para testes manuais. No retorno POST entre sites da SISP, use HTTPS (certificado local ou túnel público) e SameSite=None/Secure. A exceção local do SDK não garante que a SISP aceite callbacks HTTP ou consiga aceder ao localhost da máquina de desenvolvimento.

### Endpoint personalizado

```php
$client = new Vinti4Net(
    posID: $posId,
    posAuthCode: $posAuthCode,
    endpoint: 'https://gateway.example/TokenPayment',
);
```

Esse argumento substitui a URL completa para os pedidos do cliente, incluindo tokenização. Não é uma URL base: o SDK não acrescenta uma rota de operação ao valor fornecido. Para usar destinos personalizados diferentes, crie um cliente com o URL correspondente a cada operação. O construtor mantém a assinatura das releases anteriores.

## Preparar e enviar

```php
$client->setMerchant($referenceOf15Characters, $sessionOf15Characters);
$client->prepareTokenRequest('100'); // mínimo de futuros pagamentos; não debita o cartão
echo $client->createPaymentForm('https://merchant.example/token-response');

// Para os próximos pedidos, use novas referências/sessões persistidas pela aplicação.
$client->prepareTokenPurchase($token, '500');
$client->prepareTokenServicePayment($token, '500', 6, '227045251');
$client->prepareTokenRecharge($token, '500', 2, '9573234');
$client->prepareTokenCancellation($token); // amount=0
```

Cada chamada prepara uma operação e substitui a anterior. Gere o formulário depois da operação desejada. Os códigos são, respetivamente, `5`, `6`, `2`, `3` e `7`. A preparação de serviço/recarga tokenizados seleciona o processador Token; `prepareServicePayment` e `prepareRecharge` continuam a selecionar Payment. A reutilização do cliente não propaga o token para pagamentos tradicionais.

Para obter campos/URL sem HTML, use `Core\Token::preparePayment($params)`, que devolve `fields` e `postUrl`. Nenhum destes métodos executa um débito ou envia HTTP por si. A biblioteca não inclui um agendador de pagamentos recorrentes.

Os montantes públicos de pedido são inteiros positivos em CVE (`132`), com até 13 dígitos; cancelamento fixa zero. O mínimo de enrollment é definido pelo comerciante e pelo contrato SISP, sem constante arbitrária. A referência e a sessão merchant devem ser não vazias, sem comprimento fixo imposto pelo SDK. `generateMerchantRef()` continua disponível e gera 15 caracteres. A aplicação deve observar os limites contratados com a SISP para referências e sessões próprias. Serviço/recarga exigem entidade numérica de até 5 dígitos e referência numérica de até 9. Os idiomas disponíveis são `pt` e `en`. Tokens são strings opacas: não são convertidos em números, truncados ou aparados.

## Callbacks

```php
$response = $client->processTokenResponse($_POST, $expectedTransactionCode);

if ($response->isSuccess()) {
    // Correlacione referência/sessão com o pedido persistido antes de atualizar o estado.
    $token = $response->getToken(); // preenchido no enrollment
    $description = $response->getTokenDescription();
    $limits = $response->getTokenLimits();
}
```

Obtenha `$expectedTransactionCode` do pedido persistido na aplicação, não do callback. A verificação exige assinatura válida, versão `1`, campos obrigatórios presentes, `merchantResp='0'` e tipo compatível: enrollment A, pagamento B, cancelamento C. `processResponse()` também encaminha A/B/C automaticamente. Use `processTokenResponse($post, '7')` para validar explicitamente o contexto de cancelamento.

O plano de testes diverge da especificação/referência Node sobre cancelamento B/C. O padrão é C; Na API direta `Core\Token`, `cancelMessageType: 'B'` habilita B somente para o contexto explícito de cancelamento; a fachada usa C. A assinatura continua a usar o tipo recebido, e o montante deve ser zero. A operação de cancelar um token com sucesso devolve `SUCCESS`, distinta de `CANCELLED`, que representa uma interrupção pelo utilizador.

Erros de tipo 6 seguem a assinatura de erro existente. Respostas sem tipo são falhas ou interrupções explícitas com `UserCancelled`/`userCancelled`, nunca sucesso. Os campos originais são preservados; a variante `userCancelled` ganha um alias para integração com o wrapper atual. Recibo, código de recarga, entidade, referência e approvalCode podem ser omitidos quando não se aplicam a compra/cancelamento. Ausência equivale a vazio no hash; se um valor não vazio assinado for retirado, a assinatura deixa de coincidir. No contexto explícito de serviço/recarga, entidade e referência são obrigatórias. Os acessores podem devolver dados de respostas inválidas: só persista token/limites após validar sucesso e correlação.

## Fingerprints e testes

Versão 1: `Base64(SHA-512(posAuthCode))` inicia a concatenação; aplica-se SHA-512 binário e Base64 novamente no fim. O campo POST é `fingerprint`, a versão POST segue o SDK como `fingerprintversion`, e a query usa `FingerPrint`, `TimeStamp`, `FingerPrintVersion`. A resposta usa `resultFingerPrint` e `resultFingerPrintVersion`.

- Pedido: auth codificado, timestamp, amount × 1000, merchantRef, sessão aparada, posID, moeda, código, entidade/referência numéricas sem zeros iniciais, token opaco.
- A: auth codificado, tipo, referência, sessão, messageID, merchantResp, CP, TID, timestamp, token, descrição, limite monetário × 1000, número de operações, data YYYYMMDD, últimos quatro dígitos do PAN.
- B/C: auth codificado, tipo, referência, sessão, messageID, merchantResp, CP, TID, aprovação, montante × 1000, timestamp, recibo, código de recarga, referência e entidade. Nestes callbacks, referência e entidade mantêm os zeros.

CP/TID removem espaços; os campos textuais de callback são aparados, exceto timestamp e token. A conversão monetária usa strings decimais exatas, sem multiplicação float, e suporta até três casas decimais no cálculo da assinatura. Isso não permite pedidos públicos fracionários em CVE. Whitespace no token é preservado por decisão de string opaca; os testes preservam zeros iniciais e o conteúdo original do token.

Os vetores de pedido das cinco operações e de resposta A/B/C são dados sintéticos próprios em `tests/Fixtures/token-v1.json`. Os hashes esperados foram calculados separadamente com Python hashlib e ficam fixos no arquivo; os testes não geram os seus resultados esperados com o código Token. Há cobertura de escala decimal, zeros iniciais, alterações de payload, campos ausentes, versão, endpoints, HTTP local e reutilização da fachada.

Remover os fixtures Chuva não altera o SDK em execução. O efeito é perder a comparação direta com uma segunda implementação: os vetores próprios protegem contra regressões, mas não são evidência de compatibilidade contratual com a SISP. Uma futura homologação deve fornecer vetores oficiais assinados para completar essa validação.

Execute `composer check` para PHPUnit e PHPStan. A validação local não substitui a homologação SISP. A aplicação merchant continua responsável por consentimento, armazenamento, notificações, prevenção de duplicados, reconciliação VBVT e políticas de recorrência. Este suporte está na API Composer; `dist/standalone.php` mantém as operações anteriores.
