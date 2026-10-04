# Atualização para a v2.4.0

```bash
composer require erilshk/vinti4net:^2.4.0
```

A v2.4.0 mantém PHP `^8.1` e o namespace `Erilshk\Sisp`. Se já usa a v2.3, confira os ajustes abaixo. Para versões anteriores, consulte também as APIs legadas no final deste guia.

## Ajustes para quem usa a v2.3

### Respostas e moeda do pedido

Callbacks de erro (`messageType=6`) agora têm o fingerprint verificado com a
fórmula própria de erro. Confira `hasInvalidFingerprint()` antes de processar
sucesso ou erro; uma resposta inválida não deve alterar o estado do pedido.
Cancelamentos continuam sem fingerprint e são reconhecidos pelos campos
`merchantRef`, `merchantSession` e `UserCancelled`, sem `messageType`.

`Vinti4Response::getCurrency()` está depreciado: a resposta da SISP não informa
a moeda original do pedido. Guarde essa moeda com a encomenda. A moeda de DCC,
quando presente, pode ser consultada em `$response->dcc['currency']`; ela não
substitui a moeda original.

### Currency e Entity

```php
use Erilshk\Sisp\Currency;
use Erilshk\Sisp\Entity;

$currency = Currency::CVE; // string '132'
$numeric = Currency::toNumeric('EUR'); // string '978'
$entity = Entity::RECHARGE_ALOU; // int 2
$recharges = Entity::all('recharge'); // nome => código
```

Os parâmetros de moeda continuam a receber strings, e os de entidade recebem
inteiros. As constantes são opcionais. A moeda padrão de compra passa a aparecer
como `'132'` em `getRequest()['currency']`, em vez de `'CVE'`; o valor enviado ao
gateway permanece o mesmo. Ajuste comparações locais que dependiam de `'CVE'`.
Os códigos disponíveis em `Currency` não garantem suporte pela SISP.

### Billing e dados da conta

Use `Billing::account()` para mapear os dados da conta explicitamente:

```php
$billing = Billing::from([
    'email' => $user['email'],
    'city' => $user['city'],
    'address' => $user['address'],
])->account(
    id: (string) $user['id'],
    createdAt: '20261001',
    changedAt: '20261002',
    passwordChangedAt: '20261003',
    ageIndicator: '03',
);
```

Informe as datas reais em `YYYYMMDD`. Os indicadores de idade da conta e da senha
já não recebem valores inventados quando omitidos. `fromUser()` permanece para
compatibilidade, mas só usa `password_changed_at` para a data da senha;
`updated_at` corresponde à alteração do perfil.

`addressMatchesShipping()` aceita booleanos ou `'Y'`/`'N'` (também minúsculos).
Com `'Y'`, os campos de cobrança são copiados para a entrega. A segunda linha de
cobrança vazia recebe a primeira, e o código postal de cobrança desconhecido usa
`'0000'` na requisição.

Ao fornecer billing, informe email válido, país, cidade e primeira linha de
endereço. O país tem padrão `'132'`; `acctID`, quando informado, admite até 64
caracteres. País e subdivisão continuam sem conversão automática. Os formatos
dos demais campos seguem a [documentação de billing](billing.md), sem validação
restritiva na biblioteca.

### Integração sem Composer

O arquivo está em `dist/standalone.php`. Atualize o caminho do seu `require` ao
substituir uma cópia anterior. A integração standalone também exige PHP 8.1+.

## Principais mudanças

- `Billing::make()` e `Billing::from()` fornecem uma construção mais explícita.
- A biblioteca usa `Vinti4Exception` para suas falhas.
- `Vinti4Response` distingue sucesso, cancelamento, erro e fingerprint inválido.
- Erros descritivos da SISP ficam disponíveis em `message` e `detail`.
- `renderReceipt()` unifica recibo padrão e template personalizado.
- `renderDccReceipt()` gera o recibo DCC com os valores originais da SISP.
- `toArray()` e `toJson()` mascaram o PAN.

`preparePurchase()` exige o argumento `billing` (`array|Billing`). Para comprar sem dados adicionais de billing, passe um array vazio: `preparePurchase(1500, [])`. `setRequestParams()` aceita apenas `merchantRef`, `merchantSession`, `languageMessages` e `timeStamp`.

Quando informado, o billing é normalizado e codificado em Base64 no campo `purchaseRequest`. Os seus campos individuais não são enviados como inputs separados do formulário. Com `[]`, o campo `purchaseRequest` não é enviado.

## APIs deprecated

| API anterior | Preferir na v2.4 |
| --- | --- |
| `Billing::create()` | `Billing::from(...)->toArray()` |
| `addrMatch()` | `addressMatchesShipping()` |
| `acctID()` | `accountId()` |
| `acctInfo()` | `accountInfo()` |
| `fromUser()` | `Billing::from()` e `Billing::account()` |

Esses métodos continuam disponíveis em v2.4 para reduzir quebras. Não há necessidade de migrar para o namespace planejado da v3 nesta release.

## Checklist

1. Atualize a dependência e execute os testes.
2. Teste compra, cancelamento, código incorreto e callback aprovado no ambiente SISP.
3. Confirme a persistência de referência, sessão, transaction ID e clearing period.
4. Verifique os templates de recibo e DCC.
5. Confirme a moeda persistida no pedido, os dados reais da conta e o tratamento de fingerprints inválidos.
