# Contributing to Vinti4Net PHP SDK

Obrigado por considerar contribuir para o **Vinti4Net PHP SDK**!  
Este documento explica como você pode ajudar e quais são as melhores práticas para manter o projeto organizado e de alta qualidade.

---

## 📥 Como Contribuir

### 1. Fork & Clone

1. Faça um fork deste repositório.
2. Clone seu fork localmente:

```bash
git clone https://github.com/erilshackle/vinti4net-php.git
cd vinti4net-php
```

3. Adicione o repositório original como upstream:

```bash
git remote add upstream https://github.com/erilshackle/vinti4net-php.git
```

---

### 2. Criar uma Branch

Sempre crie uma branch específica para sua alteração:

```bash
git checkout -b nome-da-sua-branch
```

* Use nomes claros como `fix-bug-xyz` ou `feature/add-billing-helper`.

---

### 3. Código e Padrões

* PHP 8.0+ compatível
* PSR-12 coding standard
* Testes unitários sempre que possível
* Mensagens de commit claras e concisas

  * Ex.: `fix: corrigir validação de billing`
  * Ex.: `feat: adicionar helper Billing::create()`

---

### 4. Testes

* Todos os testes estão em `tests/Unit/`
* Execute os testes antes de submeter PRs:

```bash
composer install
composer test
```

* Cobertura de testes é bem-vinda. Use:

```bash
composer test-coverage # opcional
```

---

### 5. Pull Requests

1. Faça push da sua branch para seu fork:

```bash
git push origin nome-da-sua-branch
```

2. Abra um **Pull Request** para a branch `main` do repositório original.
3. Descreva:

   * Problema ou bug que você corrigiu
   * Funcionalidade que você adicionou
   * Como testar

---

### 6. Issues

* Antes de abrir uma nova issue, verifique se já existe algo parecido.
* Use títulos claros e descritivos.
* Forneça **exemplos de código** ou **logs de erro**, se possível.

---

### 7. Código de Conduta

Respeito e colaboração são fundamentais.
Todos os contribuintes devem seguir o [Código de Conduta do Contributor Covenant](https://www.contributor-covenant.org/).

---

### 8. Contato

Se precisar de ajuda ou tiver dúvidas:

* GitHub Issues: [https://github.com/erilshackle/vinti4net-php/issues](https://github.com/erilshackle/vinti4net-php/issues)
* Email do mantenedor: `erilandocarvalho@gmail.com`

---

**Obrigado por contribuir e ajudar a melhorar o SDK Vinti4Net (PHP) para toda a comunidade!** ❤️
## Documentação

A documentação usa VitePress com Node.js 22+.

```bash
npm ci
npm run docs:dev
```

`npm run docs:build` gera o site em `docs/.vitepress/dist`;
`npm run docs:preview` permite consultar o resultado localmente.
A publicação no GitHub Pages é feita pelo workflow de documentação.

O `package.json` fixa a dependência Vite do VitePress na linha 6.4.3+
para incluir as correções de segurança ausentes na dependência padrão da v1.6.4.
