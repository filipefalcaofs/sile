---
name: publicar-softwell-semit
description: >
  Publica atualizações do Viabiliza no GitLab Softwell (repo de dev) e no GitLab
  SEMIT (entrega ao cliente). Usar quando o usuário pedir para subir, publicar,
  atualizar ou fazer push para softwell, semit, homologa, produção ou main da
  prefeitura. Softwell recebe o repositório de desenvolvimento. SEMIT recebe o
  mesmo código da aplicação, inclusive o simulador REGIN, com histórico próprio.
---

# Publicar no Softwell e no SEMIT

Dois destinos. Não misturar o conteúdo nem o histórico.

| Destino | Remote / URL | O que entra |
|---|---|---|
| Softwell | `softwell` → `https://gitlab.softwell.com.br/techpulse/sile.git` | Repositório de desenvolvimento, branch atual |
| SEMIT | `https://repositoriosemit.salvador.ba.gov.br/sedur/viabiliza.git` | O mesmo código da aplicação, inclusive o simulador REGIN |

Push só com pedido explícito nesta conversa. Fast-forward. Nunca `--force` em `main` ou `homologa`.

A credencial do SEMIT fica no remote local `semit` deste repositório (`.git/config`, fora do versionamento). Qualquer sessão daqui usa `git remote get-url semit` para autenticar o clone temporário. Não gravar o token em arquivo versionado nem nesta skill. Não repetir o token na resposta. Apagar o clone temporário ao terminar.

## Qual destino

- Pedido só de Softwell, TechPulse ou repo de dev da empresa → só Softwell.
- Pedido só de SEMIT, prefeitura ou `sedur/viabiliza` → só SEMIT.
- Pedido dos dois → Softwell primeiro, SEMIT em seguida, a partir do mesmo commit.

`origin` (GitHub) não entra, salvo pedido explícito.

## Regra do SEMIT: nunca `git push semit`

"Subir para o semit", "publicar no semit" ou "semit homologa/main" **nunca** significa `git push semit <branch>` a partir deste repositório. O histórico daqui não é ancestral do SEMIT, e o push cria lá branch e tags com o histórico de desenvolvimento.

- No SEMIT só existem as branches `main` e `homologa`. Não criar nem enviar nenhuma outra (`feat/*`, `fix/*`, a branch de trabalho).
- Tag nunca é enviada deste repositório para o `semit`. A tag do SEMIT é criada no clone temporário, no commit da árvore limpa (ver "Tag SemVer").
- O remote `semit` serve só para autenticar o clone temporário (`git remote get-url semit`). Antes de qualquer `git push`, conferir o remote de destino: se for `semit`, o comando está errado.
- Se algo errado chegou ao SEMIT (branch, tag, commit de desenvolvimento), parar, avisar o usuário e só apagar a branch e as tags erradas com o aval dele. `main` e `homologa` nunca são apagadas nem forçadas.

## Softwell

```bash
git remote get-url softwell
git push -u softwell HEAD
```

A branch no Softwell é a branch atual. Se o usuário nomear outra, publicar essa. Se o remoto rejeitar por não ser fast-forward, parar e integrar. Não forçar.

O código da aplicação é o mesmo nos dois destinos, inclusive o simulador REGIN. O que fica só no Softwell é o ambiente de desenvolvimento (`.cursor`, docs internas). Seeders de demonstração e usuários fictícios também não vão para o SEMIT.

### Redeploy no Portainer (obrigatório após o push)

O push na `main` do GitLab Softwell **não** atualiza o ambiente sozinho. O stack **não** puxa a branch automaticamente — é preciso redeploy manual.

**Regra de entrega:** quando o usuário pedir publicação Softwell ou redeploy, **não parar no push**. Executar o redeploy e só encerrar com a aplicação **no ar e saudável**, com evidência (container healthy + HTTP 200 + versão esperada).

Stack: **`sile-app`** (endpoint `local`, id **172**). Imagem: **`sile:git`**. Compose: **`docker-compose.portainer-full.yml`**. URL típica: **`http://144.22.212.3:8082`**.

#### 1. Credencial Git no Portainer

O stack precisa de **Personal Access Token** do GitLab Softwell, não de senha de usuário.

- **Username:** `oauth2`
- **Password:** PAT com escopo `read_repository`
- Obter da máquina de dev (não versionar): `printf 'protocol=https\nhost=gitlab.softwell.com.br\n\n' | git credential fill`

Se `StackGitRedeploy` falhar com *HTTP Basic: Access denied*, atualizar a credencial via **StackUpdateGit** (`RepositoryAuthorizationType: 1`, `RepositoryProvider: 2` GitLab) antes de tentar de novo.

#### 2. Redeploy preferencial (MCP Portainer)

```
StackGitRedeploy(id=172, endpointId=3, RepositoryReferenceName=refs/heads/main, RepullImageAndRedeploy=false)
```

`RepullImageAndRedeploy` fica **false** — o compose usa `pull_policy: build` e rebuilda `sile:git` no host.

Se retornar *Stack is not created from git* ou `GitConfig: null`, o metadado Git do stack está corrompido — usar fallback (passo 3) ou recriar o stack como **Git repository** na UI do Portainer.

#### 3. Fallback — rebuild no host (quando Git do Portainer falha)

One-off com socket Docker e volume do compose do Portainer:

```bash
docker run --rm \
  -v /var/run/docker.sock:/var/run/docker.sock \
  -v /var/lib/docker/volumes/pt_data/_data/compose/172:/compose \
  -w /compose docker:27-cli sh -c '
    git clone --depth 1 -b main https://oauth2:<PAT>@gitlab.softwell.com.br/techpulse/sile.git /tmp/sile &&
    rsync -a --delete --exclude stack.env /tmp/sile/ /compose/ &&
    docker compose --env-file stack.env -f docker-compose.portainer-full.yml up -d --build
  '
```

Substituir `<PAT>` pelo token real (nunca commitar). Preservar `stack.env` do host.

#### 4. Verificação obrigatória (evidência fresca)

1. Container **`sile-app`**: `State=running`, health **healthy** (`docker_proxy` → `/containers/sile-app/json`).
2. HTTP: **`/up` → 200**, **`/gestao/login` → 200**, **`/portal/login` → 200**.
3. Versão: prop **`appVersion`** na página Inertia = tag publicada (ex.: `0.29.1`).
4. Mudança de frontend: nos chunks `auth-layout-*.js` / `gestao-login-*.js`, confirmar classes novas (ex.: `h-dvh`, `overflow-hidden`) — o HTML SSR não traz classes Tailwind.

Só considerar o deploy Softwell **concluído** após esses quatro pontos. Push + tag no GitLab é só metade do fluxo.

Frontend: `public/build` entra no git (o Dockerfile não roda `npm run build`). Mudança em React/TSX exige `npm run build` e commit de `public/build` antes do push — senão o redeploy sobe UI antiga mesmo com código PHP atualizado.

## SEMIT

Produção é `main`. Homologação é `homologa`. Não criar nem atualizar branch `prod`.

Se o pedido não nomear a branch, o mesmo commit vai para `homologa` e para `main`. Se nomear só uma, atualizar só essa.

O histórico do repositório de trabalho não é ancestral do SEMIT. Nunca fazer `git push` da branch de trabalho para o SEMIT.

### Atalho para mudança pequena

Quando o commit publicado altera poucos arquivos da aplicação (ex.: `git diff --stat <ultimo-commit-publicado> HEAD`) e o SEMIT já tem esses arquivos idênticos à versão anterior do desenvolvimento, não é preciso regenerar a árvore inteira. No clone temporário: copiar só os arquivos alterados para `app-src/`, atualizar `VERSION` (raiz e `app-src/`) para a tag, e seguir direto aos passos 8 e 9. Se o script listar referências proibidas em arquivos que o SEMIT já tem limpos, é esperado: o SEMIT guarda a versão corrigida. Mudança grande ou em arquivo que o SEMIT mantém diferente do desenvolvimento exige o fluxo completo abaixo.

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
6. Copiar para a raiz do clone os arquivos que o script deixou ao lado de `app-src/`: `Dockerfile`, `DockerFile_supervisord`, `DockerFile_schedule` e `.dockerignore`. Eles vêm de `docker/okd/` e seguem o padrão S2I (imagem `registry.salvador.ba.gov.br/library/php-oracle:8.4`, usuário 1001, `assemble`). Não reescrever esses arquivos a partir do Dockerfile de Portainer. Não trocar esse `FROM` de volta para `viabiliza/php-84`. Não deixar `Dockerfile` dentro de `app-src`. O shim do Composer fica em `app-src/.s2i/`.
7. Se `docker-compose.portainer-*.yml` mudou, atualizar os arquivos da raiz. O seed do compose de aplicação roda `php artisan db:seed --force` e carrega só catálogo oficial. Sem `DemonstracaoClienteSeeder` e sem `SILE_DEMO_DATA`.
8. Se o payload alterou `resources/js`, `resources/css` ou o menu, em `app-src`: `npm ci && npm run build && npx vitest run resources/js/navigation/gestao-nav.test.ts`. Não versionar `node_modules`.
9. Rodar de novo a varredura do script sobre a árvore final. Só seguir com saída 0.

### O que o SEMIT não leva

- IDE e ferramentas de desenvolvimento: `.cursor/`, `.claude/`, `.codex/`, `.agents/`, `.ai/`, `.planning/`, `.github/`, `docs/`, `AGENTS.md`, `.mcp.json`, `boost.json`
- Compose e scripts só de máquina local: `docker-compose.yml`, `scripts/portainer-build-push.sh`, scripts de ponto de função, `scripts/convert-cnae-xlsx.py`
- Massa fictícia: `*DevSeeder`, `CompanySeeder`, `DevAdminSeeder`, `DemonstracaoClienteSeeder`, `DemoMode`
- Comentário ou URL que aponte para `.cursor/`, GitHub pessoal ou o fluxo interno do GitLab Softwell

O simulador REGIN vai inteiro: tela, rotas, controller, service, catálogo, model, factory, comando, migrations, `protocolos-sedur.json`, parâmetro `features.simulacao_protocolo` e os testes desse fluxo. Não reescrever menu, seeder nem serviço para tirá-lo da entrega.

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

## Tag SemVer — sempre, nos dois destinos

Toda publicação na Softwell e na SEMIT, neste projeto e em qualquer outro, termina com uma tag anotada `vX.Y.Z` no commit empurrado. Sem essa tag a publicação não está completa.

A sequência é a do remote de destino. A maior tag `vX.Y.Z` já publicada sobe um passo:

- correção compatível: PATCH (`v1.2.3` → `v1.2.4`)
- funcionalidade compatível: MINOR, PATCH zera (`v1.2.3` → `v1.3.0`)
- mudança incompatível: MAJOR, o resto zera (`v1.2.3` → `v2.0.0`)

Sem tag anterior, a primeira é `v0.1.0`, ou o número que o arquivo `VERSION` já tiver. Se o projeto tem `VERSION`, o arquivo fica igual à tag no mesmo commit.

A mensagem da tag explica o que foi enviado: destino, branches e o conteúdo, em português. Uma tag por publicação. Nunca reutilizar, nunca mover tag antiga, nunca `git push --tags`.

- Softwell: `git tag -a vX.Y.Z -m "..." && git push softwell vX.Y.Z`
- SEMIT: a tag aponta para o commit da árvore limpa, no clone temporário, e vai com `git push origin vX.Y.Z`

```bash
git tag -a v0.2.0 -m "$(cat <<'EOF'
SEMIT homologa e main.

Importação versionada da planilha de regras de tratamento no Motor de regras.
Quem importa pode publicar o próprio rascunho. Arquivo repetido não abre outra versão.

EOF
)"
git push origin v0.2.0
```

Conferir com `git ls-remote` se `main` e `homologa` apontam para o commit esperado. Apagar o clone em `/tmp`.

Não fazer `git push semit` a partir da branch de desenvolvimento, nem com tag, nem com `--tags`. O remote `semit` só autentica. A publicação continua sendo o commit da árvore limpa, no clone temporário, e o único destino de push é `origin` desse clone (`homologa` e/ou `main`).

Ao terminar, rodar `git ls-remote --heads semit`: só podem aparecer `main` e `homologa`. Qualquer outra branch ou tag cujo commit não seja da árvore limpa do SEMIT é erro a corrigir com o aval do usuário.
