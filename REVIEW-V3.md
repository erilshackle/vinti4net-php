# Revisão dos arquivos auxiliares da v3

## Alterações aplicadas

- Documentação e exemplos migrados da API v2 para a API v3.
- `llms.txt` atualizado e `llms-full.txt` criado.
- `composer.json` recebeu scripts `validate` e `check`, cobertura Clover e links de suporte.
- CI atualizada para PHP 8.1, 8.2, 8.3 e 8.4.
- Cobertura executada separadamente em PHP 8.3 e enviada ao Codecov por OIDC.
- Action de documentação criada para validar pull requests e publicar o site no GitHub Pages após push em `main`.
- `mkdocs.yml` normalizado e atualizado para Material for MkDocs.
- Dependências da documentação isoladas em `requirements-docs.txt`.

## Estrutura necessária para MkDocs

O diretório `docs/` do repositório deve conter os arquivos Markdown-fonte:

```text
docs/
├── index.md
├── installation.md
├── quickstart.md
├── payments.md
├── requests.md
├── refunds.md
├── receipt.md
├── billing.md
├── responses.md
├── architecture.md
├── api.md
├── about.md
└── assets/
    ├── logo.png
    └── icon.png
```

O ZIP `docs.zip` analisado anteriormente contém HTML já compilado. Esse conteúdo é saída de build e não substitui os arquivos Markdown acima.

## Ativar a publicação

No GitHub, abra `Settings > Pages` e selecione `GitHub Actions` em `Build and deployment > Source`. Depois disso, `.github/workflows/docs.yml` publicará o site quando os arquivos de documentação forem alterados em `main`.

## Validação local

```bash
python -m pip install -r requirements-docs.txt
mkdocs serve
mkdocs build --strict
```

O `mkdocs.yml` foi validado com Material for MkDocs 9.7.7 usando um conjunto temporário completo de páginas-fonte.
