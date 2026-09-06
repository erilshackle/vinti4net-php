# Sobre esta documentação

Esta documentação apresenta o Vinti4Net PHP SDK, uma biblioteca criada para facilitar a integração de aplicações PHP com o sistema de pagamentos Vinti4/SISP em Cabo Verde.

O SDK oferece uma interface para:

- compras com 3D Secure;
- pagamentos de serviço;
- recargas;
- reembolsos;
- validação das respostas;
- recibos normais e DCC.

---

## Organização da documentação

- **Guia rápido:** primeira integração, do pagamento ao callback.
- **Pagamentos:** compra, serviço e recarga.
- **Requisições:** parâmetros, limites e exemplos completos.
- **Billing:** dados de faturação e suporte 3DS.
- **Reembolsos:** dados necessários e fluxo de devolução.
- **Respostas:** estados, mensagens e dados retornados.
- **Recibos:** template padrão, personalizado e DCC.
- **API Reference:** classes, métodos e propriedades públicas.
- **Guias técnicos:** fingerprint, segurança, campos e diagnóstico.

Esta documentação corresponde à versão **2.2.x** e mantém o namespace `Erilshk\Sisp`.

---

## Projeto comunitário

Este SDK não é oficial da SISP. Ele foi desenvolvido por [Eril Shackle](https://github.com/erilshackle) para simplificar a integração com a Vinti4Net, mas não representa oficialmente a SISP nem os seus produtos.

As credenciais, regras comerciais e instruções fornecidas diretamente pela SISP ao comerciante têm prioridade.

---

## Conteúdo técnico

A documentação foi organizada com auxílio de inteligência artificial, mas os exemplos e limites técnicos desta versão foram revistos a partir do código da v2.2 e das regras utilizadas pela integração.

Não altere a lógica sensível do fingerprint com base apenas em exemplos. A ordem dos campos e a forma de cálculo devem permanecer de acordo com o contrato SISP implementado pela biblioteca.

---

## Isenção de responsabilidade

- O SDK é disponibilizado para uso próprio e comunitário.
- O uso em produção deve respeitar o contrato SISP e a legislação aplicável.
- O comerciante é responsável por proteger credenciais e dados dos clientes.
- O callback deve ser validado antes de confirmar qualquer pagamento.

Para dúvidas, problemas ou contribuições, acesse o [repositório no GitHub](https://github.com/erilshackle/vinti4net-php) ou entre em [contacto](mailto:erilandocarvalho@gmail.com).
