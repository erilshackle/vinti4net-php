# Changelog

Todas as alterações relevantes deste projeto serão documentadas neste arquivo.

O formato segue [Keep a Changelog](https://keepachangelog.com/pt-BR/1.1.0/) e o projeto utiliza [Versionamento Semântico](https://semver.org/lang/pt-BR/).

## [Não lançado]

### Pendente

- Homologação dos fluxos reais de compra, serviço, recarga e reembolso com a SISP.
- Validação de callbacks reais anonimizados.

## [3.0.0-beta.1] - 2026-09-05

### Adicionado

- Nova API com `purchase()`, `servicePayment()`, `recharge()` e `refund()`.
- `TransactionRequest::form()` e `TransactionRequest::send()`.
- Exceções dedicadas da biblioteca.
- Builder `Billing::make()` e criação por `Billing::from()`.
- Resposta normalizada e imutável por `Vinti4Response`.
- `renderReceipt()` unificado para o recibo padrão e templates PHP, HTML e HTM.
- `renderDccReceipt()` para o recibo bilíngue DCC exigido pela SISP.
- Testes unitários e de integração para a API v3.
- Documentação MkDocs, arquivos para LLMs e guia de migração.
- GitHub Actions para PHP 8.1–8.4, cobertura e GitHub Pages.

### Alterado

- Namespace público alterado para `Eril\Sisp`.
- Operações agora são independentes e não compartilham estado mutável.
- Referência e sessão são informadas ao criar a operação.
- Valores são normalizados sem float ou BCMath.
- Billing agora valida e normaliza os campos aceitos.
- Métodos de resposta não usam o prefixo `get`.
- Serialização de respostas não expõe payload bruto nem PAN.
- Sistema de recibos consolidado num único renderer.

### Removido

- Métodos `preparePurchase()`, `prepareServicePayment()`, `prepareRecharge()` e `prepareRefund()`.
- `setMerchant()`, `setRequestParams()`, `createPaymentForm()` e `getRequest()`.
- Getters antigos de `Vinti4Response`.
- Implementações duplicadas de recibos.

[Não lançado]: https://github.com/erilshackle/vinti4net-php/compare/v3.0.0-beta.1...HEAD
[3.0.0-beta.1]: https://github.com/erilshackle/vinti4net-php/compare/v2.0.0...v3.0.0-beta.1
