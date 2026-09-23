---
name: publicar-softwell-semit
description: >
  Publica atualizações do Viabiliza no GitLab Softwell (repo de dev) e no GitLab
  SEMIT (entrega ao cliente). Usar quando o usuário pedir para subir, publicar,
  atualizar ou fazer push para softwell, semit, homologa, produção ou main da
  prefeitura. Softwell recebe o repositório de desenvolvimento. SEMIT recebe só
  o código de execução, com histórico próprio.
---

# Publicar no Softwell e no SEMIT

Dois destinos. Não misturar o conteúdo nem o histórico.

| Destino | Remote / URL | O que entra |
|---|---|---|
| Softwell | `softwell` → `https://gitlab.softwell.com.br/techpulse/sile.git` | Repositório de desenvolvimento, branch atual |
| SEMIT | `https://repositoriosemit.salvador.ba.gov.br/sedur/viabiliza.git` | Só o código para instalar, executar e manter |

Push só com pedido explícito nesta conversa. Fast-forward. Nunca `--force` em `main` ou `homologa`.

A credencial do SEMIT fica no remote local `semit` deste repositório (`.git/config`, fora do versionamento). Qualquer sessão daqui usa `git remote get-url semit` para autenticar o clone temporário. Não gravar o token em arquivo versionado nem nesta skill. Não repetir o token na resposta. Apagar o clone temporário ao terminar.

## Qual destino

- Pedido só de Softwell, TechPulse ou repo de dev da empresa → só Softwell.
- Pedido só de SEMIT, prefeitura ou `sedur/viabiliza` → só SEMIT.
- Pedido dos dois → Softwell primeiro, SEMIT em seguida, a partir do mesmo commit.

`origin` (GitHub) não entra, salvo pedido explícito.

## Softwell

```bash
git remote get-url softwell
git push -u softwell HEAD
```

A branch no Softwell é a branch atual. Se o usuário nomear outra, publicar essa. Se o remoto rejeitar por não ser fast-forward, parar e integrar. Não forçar.

O Softwell leva o ambiente de desenvolvimento (`.cursor`, docs, testes, simulador). Isso não vai para o SEMIT.

## SEMIT

Produção é `main`. Homologação é `homologa`. Não criar nem atualizar branch `prod`.

Se o pedido não nomear a branch, o mesmo commit vai para `homologa` e para `main`. Se nomear só uma, atualizar só essa.

O histórico do repositório de trabalho não é ancestral do SEMIT. Nunca fazer `git push` da branch de trabalho para o SEMIT.

### Montar a entrega

1. Anotar o commit publicado no Softwell (`git rev-parse HEAD`).
2. Clonar a `main` do SEMIT em `/tmp` (shallow).
3. Rodar o script e gerar a árvore filtrada:

```bash
python3 .cursor/skills/publicar-softwell-semit/scripts/montar-entrega-semit.py \
  . <commit> /tmp/viabiliza-entrega
```

4. Se o script sair com código 2, corrigir no diretório gerado as referências que ele listar. Não trazer de volta arquivo excluído.
5. Substituir `app-src/` do clone pelo `app-src/` gerado.
6. Copiar para a raiz do clone os arquivos que o script deixou ao lado de `app-src/`: `Dockerfile`, `DockerFile_supervisord`, `DockerFile_schedule` e `.dockerignore`. Eles vêm de `docker/okd/` e seguem o padrão S2I do SIGVISA (imagem `viabiliza/php-84`, usuário 1001, `assemble`). Não reescrever esses arquivos a partir do Dockerfile de Portainer. Não deixar `Dockerfile` dentro de `app-src`. O shim do Composer fica em `app-src/.s2i/`.
7. Se `docker-compose.portainer-*.yml` mudou, atualizar os arquivos da raiz. O seed do compose de aplicação roda `php artisan db:seed --force` e carrega só catálogo oficial. Sem `DemonstracaoClienteSeeder` e sem `SILE_DEMO_DATA`.
8. Se o payload alterou `resources/js`, `resources/css` ou o menu, em `app-src`: `npm ci && npm run build && npx vitest run resources/js/navigation/gestao-nav.test.ts`. Não versionar `node_modules`.
9. Rodar de novo a varredura do script sobre a árvore final. Só seguir com saída 0.

### O que o SEMIT não leva

- IDE e ferramentas de desenvolvimento: `.cursor/`, `.claude/`, `.codex/`, `.agents/`, `.ai/`, `.planning/`, `.github/`, `docs/`, `AGENTS.md`, `.mcp.json`, `boost.json`
- Compose e scripts só de máquina local: `docker-compose.yml`, `scripts/portainer-build-push.sh`, scripts de ponto de função, `scripts/convert-cnae-xlsx.py`
- Simulador REGIN: tela, rotas, controller, service, catálogo, model, factory, comando, migrations `regin_simulacao_execucoes`, `protocolos-sedur.json`, testes desse fluxo
- Massa fictícia: `*DevSeeder`, `CompanySeeder`, `DevAdminSeeder`, `DemonstracaoClienteSeeder`, `DemoMode`, parâmetro `features.simulacao_protocolo`
- Comentário ou URL que aponte para `.cursor/`, GitHub pessoal ou o fluxo interno do GitLab Softwell

`DatabaseSeeder` no SEMIT chama só catálogo oficial (CNAE, risco, LOUOS, tratamento, TLL, zonas, vias, tipos de imóvel, parâmetros, papéis, feriados nacionais fixos, camadas, setor Análise Locacional, textos). Não cria usuário, empresa nem processo.

A integração real do REGIN permanece. Anthropic como provedor configurável da aplicação permanece.

### Publicar

No clone, um commit em português, sem trailer de ferramenta:

```bash
git add -A
git commit -m "feat: publica atualização do Viabiliza"
git push origin HEAD:homologa   # se for o destino pedido
git push origin HEAD:main       # se for o destino pedido
```

Tag `vX.Y.Z` só quando `VERSION` mudar. Enviar só essa tag: `git push origin vX.Y.Z`. Nunca `git push --tags`.

Conferir com `git ls-remote` se `main` e `homologa` apontam para o commit esperado. Apagar o clone em `/tmp`.

Não fazer `git push semit` a partir da branch de desenvolvimento. O remote `semit` só autentica. A publicação continua sendo o commit da árvore limpa, no clone temporário.
