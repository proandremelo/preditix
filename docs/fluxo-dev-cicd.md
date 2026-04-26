# Fluxo de desenvolvimento e CI/CD

## Branches oficiais

- `main`: producao
- `develop`: integracao continua (ambiente `desenv`)
- `release/*`: preparacao para homologacao
- `feature/*`: desenvolvimento de funcionalidade
- `hotfix/*`: correcao urgente de producao

## Regra de trabalho diario

1. Criar branch de feature a partir de `develop`.
2. Implementar mudanca pequena e objetiva.
3. Abrir PR para `develop` com checklist completo.
4. Aguardar CI verde.
5. Fazer revisao cruzada (front revisa back e back revisa front).
6. Fazer merge em `develop` e validar no `desenv`.

## Regra de release

1. Criar `release/AAAA-MM-N` a partir de `develop`.
2. Validar em `homol`.
3. Se aprovado, mergear em `main`.
4. Deploy automatico para `prod`.

## Boas praticas de PR

- PR pequeno (idealmente ate ~400 linhas mudadas).
- Uma responsabilidade por PR.
- Sempre incluir evidencias de teste.
- Nao commitar segredo (tokens/senhas/chaves).

## Definicao de pronto (DoD)

- CI passou.
- Checklist do PR completo.
- Revisao cruzada concluida.
- Sem regressao funcional conhecida.
- Documento/contrato atualizado quando aplicavel.

## Branch protection (configurar no GitHub)

### `develop`
- Exigir pull request antes de merge.
- Exigir ao menos 1 aprovacao.
- Exigir status checks do CI.
- Bloquear push direto.

### `main`
- Exigir pull request antes de merge.
- Exigir ao menos 1 aprovacao.
- Exigir status checks do CI.
- Restringir quem pode mergear.
- Bloquear push direto.
