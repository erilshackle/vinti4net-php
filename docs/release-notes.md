# Notas de versão

Mudanças de comportamento e novas APIs das versões recentes. A linha v2 usa
PHP 8.1+ e o namespace `Erilshk\Sisp`.

## 2.5.0 — em desenvolvimento

### Tokenização

- A API Composer passa a preparar adesão de token (código `5`), compra com
  token (`6`), serviço (`2`), recarga (`3`) e cancelamento (`7`, montante zero)
  através dos métodos `prepareToken*()` de `Vinti4Net`.
- O processador `Core\Token` usa fingerprints v1 independentes dos pagamentos
  tradicionais. Os callbacks assinados A/B/C são validados e expõem token,
  descrição e limites do enrollment através de `Vinti4Response`.
- Os métodos de serviço, recarga e compra tokenizados recebem `$token` como
  primeiro argumento. O construtor e os métodos já publicados mantêm as suas
  assinaturas. A integração `dist/standalone.php` mantém as operações anteriores.
- Os endpoints padrão são enrollment, pagamento e cancelamento em
  `https://mc.vinti4net.cv/BizMPIOnUsSisp`. O argumento `endpoint` já existente
  em `Vinti4Net` também substitui o URL completo usado na tokenização, sem
  novos argumentos nem setters.
- Callbacks HTTP em `localhost`, `127.0.0.1` e `::1` são aceites para
  desenvolvimento local; a SISP pode exigir HTTPS acessível para o retorno
  real. O enrollment recolhe os dados do cartão e o OTP na página da SISP.

### Referências e billing

- `merchantRef` e `merchantSession` exigem valores não vazios, sem comprimento
  fixo imposto pelo SDK. `generateMerchantRef()` continua público e estático,
  gerando referências de 15 caracteres nos modos padrão e aleatório.
- Países ISO 3166-1 alpha-2 atribuídos são convertidos para códigos numéricos
  ISO de três dígitos nos endereços de faturação e entrega; códigos numéricos
  e valores sem mapeamento são preservados.

### Exemplos e validação

- Foram acrescentados exemplos de utilização em português e inglês e testes
  independentes para fingerprints, validação de callbacks, endpoints,
  callbacks locais e reutilização da fachada. Os vetores são sintéticos e não
  substituem a homologação nem vetores oficiais da SISP.

## 2.4.0

### Moedas e entidades

- **`Currency`** reúne constantes string como `Currency::CVE` (`'132'`) e
  `Currency::EUR` (`'978'`). `Currency::toNumeric('EUR')` converte o nome para
  o código numérico; códigos de três dígitos são preservados. Os parâmetros
  continuam a receber strings, e a existência de uma constante não garante
  que a SISP aceite essa moeda.
- **Moeda padrão:** `getRequest()['currency']` passa a devolver `'132'`, em vez
  de `'CVE'`, quando a moeda é omitida. O código enviado ao gateway permanece
  igual; apenas comparações locais com o nome precisam de ajuste.
- **`Entity`** fornece constantes inteiras para pagamentos de serviço e
  recargas, como `Entity::RECHARGE_ALOU`. `Entity::all()` devolve `nome => código`
  e aceita uma categoria, por exemplo `Entity::all('recharge')`.

```php
use Erilshk\Sisp\Currency;
use Erilshk\Sisp\Entity;

Currency::toNumeric('CVE'); // '132'
Entity::all('recharge'); // ['RECHARGE_ALOU' => 2, ...]
```

### Billing e conta do cliente

- **`Billing::account()`** permite informar ID, datas, indicadores de idade e
  atividade suspeita com parâmetros definidos. É uma alternativa ao mapeamento
  de um array de utilizador com `fromUser()`, que continua depreciado.
- **Histórico da conta:** indicadores de idade da conta e da senha são omitidos
  quando não fornecidos. Em `fromUser()`, `updated_at` informa a alteração do
  perfil; a alteração da senha usa apenas `password_changed_at`. As datas
  enviadas explicitamente devem estar em `YYYYMMDD`.
- **Endereços:** `addressMatchesShipping()` aceita booleanos e strings `Y`/`N`,
  inclusive minúsculas. Com `Y`, os campos de cobrança são copiados para a
  entrega. A segunda linha de cobrança vazia recebe a primeira; o código postal
  de cobrança desconhecido recebe `0000` ao gerar o pedido.
- **Validação:** ao fornecer billing, são verificados os campos obrigatórios,
  o email e o limite de 64 caracteres de `acctID`. País e subdivisão são
  preservados sem conversão automática; os campos opcionais não recebem
  validações restritivas de formato.

```php
$billing->account(
    id: (string) $user['id'],
    createdAt: '20260901',
    passwordChangedAt: '20261001',
    ageIndicator: '04',
)->addressMatchesShipping('Y');
```

As datas e o indicador do exemplo representam um histórico informado pelo
comerciante; substitua pelos dados reais do cliente.

### Respostas de pagamento

- **Erros assinados:** callbacks com `messageType=6` são verificados usando a
  fórmula própria de erro. Fingerprint ausente ou inválido resulta em
  `INVALID_FINGERPRINT`; consulte `hasInvalidFingerprint()` antes de usar a
  resposta para alterar o estado do pedido.
- **Cancelamento:** continua a ser reconhecido sem fingerprint, pelos campos
  `merchantRef`, `merchantSession` e `UserCancelled`, sem `messageType`.
  Um campo de cancelamento não sobrepõe uma resposta assinada de sucesso ou erro.
- **`getCurrency()` depreciado:** a resposta da SISP não devolve a moeda original
  da compra. Use a moeda guardada no pedido. Quando existir DCC,
  `dcc['currency']` representa a moeda da conversão.

Para obter a moeda original, use o valor persistido pela aplicação:

```diff
- $currency = $response->getCurrency();
+ $currency = $order['currency'];
```

## 2.3.0

- **Referências:** `generateMerchantRef()` inclui um componente aleatório para
  reduzir colisões entre pedidos criados no mesmo segundo. Referência e sessão
  devem ter exatamente 15 caracteres, inclusive quando fornecidas pela aplicação.
- **Reembolsos:** `renderRefundReceipt()` usa um template dedicado. O valor da
  compra original vem da aplicação, pois a resposta do fornecedor pode trazer zero.
- **Dados da resposta:** `isDccEnabled()` indica se há conversão DCC e
  `getClearingPeriod()` permite consultar o período de compensação.

## 2.2.1

- **Billing:** `Billing::from()` aceita nomes amigáveis e campos SISP, normaliza
  telefones e permite informar endereço de entrega e dados da conta. Valores
  explícitos prevalecem sobre os dados legados do utilizador.
- **Erros e estados:** `Vinti4Exception` centraliza as exceções da biblioteca.
  `Vinti4Response` distingue sucesso, erro, cancelamento e fingerprint inválido;
  `hasFailed()` permite consultar falhas.
- **Recibos:** `renderReceipt()` aceita o template padrão ou templates PHP/HTML.
  `renderDccReceipt()` apresenta os valores originais da SISP, sem recalcular a
  conversão ou tratar o markup como percentagem.
- **Proteção dos dados:** `toArray()` e `toJson()` mascaram o PAN, e os valores
  dos formulários gerados recebem escape antes de serem inseridos no HTML.
- **Compatibilidade:** os métodos de pagamento e aliases legados continuam
  disponíveis. Prefira `Billing::from()`, `addressMatchesShipping()`,
  `accountId()` e `accountInfo()` nas novas integrações.

Veja a [referência da API](api.md), os [dados de billing](billing.md) e o
[processamento de respostas](responses.md) para exemplos de utilização.
