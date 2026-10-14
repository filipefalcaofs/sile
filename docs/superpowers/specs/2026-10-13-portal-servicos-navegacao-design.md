# Portal do cidadão — serviços claros e de fácil acesso

Data: 2026-10-13
Escopo aprovado: opção 1 (navegação e descoberta). Fluxos internos (wizards) ficam fora.

## Objetivo

Todo serviço do portal tem uma entrada visível, com nome simples, a partir de qualquer tela.
O painel começa pelo "o que você quer fazer". Nenhum serviço fica alcançável só pela URL e
nenhum card parece clicável sem ser.

### Critérios de sucesso

1. Todo serviço do portal aparece no menu lateral **e** no painel.
2. A sidebar, o painel e a landing pública leem um catálogo único (`portal-nav.ts`), como a gestão já faz com `gestao-nav.ts`.
3. Um usuário logado que abre a consulta de viabilidade continua dentro do portal, com a sidebar.
4. Os indicadores do painel abrem a lista de solicitações já filtrada.
5. Nenhum card de serviço da landing fica sem link.

## Diagnóstico (estado atual)

| Problema | Onde |
|---|---|
| Procurações (`/portal/procuracoes`) sem nenhum link no portal | — |
| Histórico de consultas (`/portal/viabilidade/historico`) sem nenhum link | — |
| Consulta de viabilidade só aparece no estado vazio do painel | `dashboard.tsx:325` |
| Minhas empresas fora do menu (só no atalho do fim do painel) | `portal-layout.tsx:14` |
| Menu com 4 itens; "Minha conta" antes de "Início" | `portal-layout.tsx:14-30` |
| Perfil e senha escondidos no menu do avatar | `app-header.tsx:91` |
| Consulta logada usa o `PublicShell` (sem sidebar, com botão "Entrar") | `consulta.tsx:96,463` |
| O logo da sidebar leva à landing pública `/portal` | `app-sidebar.tsx:165` |
| Os 3 indicadores do painel levam ao mesmo lugar, sem filtro | `dashboard.tsx:155-183` |
| Atalhos de serviço no fim do painel; card "Minha conta" redundante | `dashboard.tsx:362-407` |
| 3 dos 4 cards da landing sem `href`; Renovação ausente | `home.tsx:36-62` |
| A landing mostra "Entrar" para quem já está logado | `home.tsx` |

## Decisões

- **Catálogo único no front** (`resources/js/navigation/portal-nav.ts`), puro TypeScript e testável no Vitest (`environment: node`). Os ícones são chaves de string, mapeadas para componentes num `.tsx` separado, igual à gestão.
- **Os itens não dependem de toggle.** As páginas já degradam de forma comunicada (consulta desativada, procurações desativadas, novas solicitações indisponíveis). Procurações desligadas ainda permitem ver e revogar as existentes. Nenhum shared prop novo.
- **"Convite" continua.** É termo de domínio (`AnalysisStatus::EmConvite`) e já é usado em toda a tela de solicitações. O painel só acrescenta uma explicação em linguagem simples.
- **Notificações entram no menu** (grupo Início), além do sino. Dá descoberta e resolve o grupo de item único proibido pela normativa.
- **Sem Ctrl+K no portal** (YAGNI: o catálogo tem 9 itens).
- O histórico de consultas é tela-filha da consulta (normativa: filho não entra no menu). Ele ganha links na consulta (quando logado) e no painel.

## Arquitetura da informação

### Sidebar

| Grupo | Item | Rota | Ícone |
|---|---|---|---|
| Início | Meu painel | `/portal/painel` | grid |
| Início | Notificações | `/portal/notificacoes` | bell |
| Serviços | Consultar viabilidade | `/portal/viabilidade` | search |
| Serviços | Minhas solicitações | `/portal/solicitacoes` | file |
| Serviços | Renovar viabilidade | `/portal/renovacao` | check-circle |
| Serviços | Minhas empresas | `/portal/empresas` | list |
| Serviços | Procurações | `/portal/procuracoes` | group |
| Minha conta | Meus dados | `/portal/conta/perfil` | user |
| Minha conta | Histórico de acessos | `/portal/acessos` | shield |

A ordem dos grupos é fixa: Início → Serviços → Minha conta. Dentro de Serviços, a ordem segue o uso mais frequente.
O item ativo segue o `resolveActiveHref` existente: `/portal/viabilidade/historico` acende "Consultar viabilidade" e `/portal/solicitacoes/nova` acende "Minhas solicitações".

### Ações de serviço ("O que você quer fazer?")

Lista `PORTAL_ACOES` no mesmo catálogo, com verbo, descrição curta e destino. É usada no painel e na landing.

| Ação | Descrição | Destino | Pública (landing) |
|---|---|---|---|
| Consultar viabilidade | Veja se a atividade pode funcionar no endereço, antes de pedir. | `/portal/viabilidade` | sim |
| Solicitar viabilidade | Protocole o pedido e acompanhe a análise. Atividades de baixo risco têm resposta automática. | `/portal/solicitacoes/nova` | sim |
| Renovar viabilidade | Prorrogue uma viabilidade provisória. | `/portal/renovacao` | sim |
| Gerenciar empresas | Cadastre empresas e mantenha CNAEs atualizados. | `/portal/empresas` | não |
| Procurações | Autorize alguém a agir em seu nome, ou atue por quem autorizou você. | `/portal/procuracoes` | sim |

Na landing, o "Fluxo expresso" deixa de ser card de serviço (não é serviço, é característica da solicitação) e passa para a descrição de "Solicitar viabilidade". As rotas protegidas mandam o visitante ao login, que retorna à rota pretendida.

### Invariantes (`assertPortalNavHealth`)

- Grupos exatamente `inicio`, `servicos`, `conta`, nessa ordem.
- Sem `href` duplicado na sidebar e sem ícone repetido.
- Nenhum grupo com um único item.
- Todo item de Serviços é coberto por pelo menos uma ação de `PORTAL_ACOES` (o `href` da ação começa com o `href` do item). Isso garante que tudo o que está no menu também está no painel.
- Toda ação aponta para uma rota sob `/portal/`.

## Mudanças por tela

### `portal-layout.tsx`
Monta os grupos a partir de `PORTAL_NAV_GROUPS` e de `portal-nav-icons.tsx`, sem lista local. Passa `logoHref="/portal/painel"`.

### `app-shell.tsx` / `app-sidebar.tsx`
Nova prop opcional `logoHref` (padrão `homeHref`), usada só no link do logo. O `homeHref` continua alimentando o sino (`/portal/notificacoes`) e o `resolveActiveHref`. A gestão não muda.

### Painel (`dashboard.tsx`)
Nova ordem:
1. Cabeçalho "Olá, {primeiro nome}" com o subtítulo "O que você precisa resolver hoje?".
2. Bloco "Precisa da sua atenção" / "Tudo em dia", sem mudança de lógica. O título do item muda de `Convite — {protocolo}` para `Convite para responder — {protocolo}`, com uma linha explicando que a SEDUR pediu informação ou documento.
3. **"O que você quer fazer?"**: grade com `PORTAL_ACOES` (cards com ícone, verbo, descrição e seta).
4. Indicadores, cada um com link filtrado:
   - Em análise → `/portal/solicitacoes?status=em_analise`
   - Convites para responder (antes "Em Convite") → `/portal/solicitacoes?status=em_pendencia`
   - Total de solicitações (antes "Meus Processos") → `/portal/solicitacoes`
5. "Minhas solicitações recentes", sem mudança, mais o link "Minhas consultas de viabilidade" ao lado de "Ver todas".

Saem o card "Minha conta" e o atalho "Meus acessos" (os dois ficam no grupo Minha conta da sidebar).

### Minhas solicitações (`SolicitacaoController@index` + `solicitacoes/index.tsx`)
- Backend: novo parâmetro `status`, validado contra `ViabilityRequestStatus` (valor inválido é ignorado, nunca dá 500). Ele entra em `filters.status` e é preservado na paginação (`withQueryString`). O escopo do requerente efetivo continua igual.
- Front: quando `filters.status` está presente, mostra um chip "Filtrando por: {rótulo público} · Limpar filtro". Sem select novo.

### Consulta de viabilidade (`viabilidade/consulta.tsx`)
- `Consulta.layout` escolhe o shell pelo usuário: com `auth.user` usa o `PortalLayout`, sem ele usa o `PublicShell`. A escolha fica numa função pura `shellDaConsulta(user)`, testada.
- Quando logado, aparece o link "Ver minhas consultas anteriores" → `/portal/viabilidade/historico`.

### Landing (`home.tsx`)
- A seção de serviços usa `PORTAL_ACOES` filtrado por `publica`. Todo card tem link.
- Com `auth.user`, os botões "Entrar" viram "Ir para o meu painel" (`/portal/painel`).

### Normativa (`.cursor/rules/menu-navegacao.mdc`)
A seção "Portal do cidadão" passa a apontar `portal-nav.ts` como fonte de verdade, com os grupos e as invariantes acima.

## Fora de escopo
- Wizards de solicitação e de renovação, e as mensagens internas.
- Busca Ctrl+K no portal.
- Esconder itens conforme os toggles de funcionalidade.
- Alterar o vocabulário de domínio ("convite", "protocolo").

## Testes

| Camada | Teste |
|---|---|
| Vitest | `portal-nav.test.ts`: ordem dos grupos, `assertPortalNavHealth() == []`, cobertura menu→ações, ações públicas sem rota de conta, hrefs dos indicadores |
| Vitest | `shellDaConsulta`: logado → `portal`, anônimo → `publico` |
| Vitest | `sidebar-active.test.ts`: casos do portal (histórico acende consulta; nova acende solicitações; painel exato) |
| PHPUnit | `SolicitacaoController@index`: `status` filtra, valor inválido é ignorado, `filters.status` é exposto, escopo do efetivo mantido |
| PHPUnit | `ConsultaViabilidadePaginaTest`: a página continua pública e funciona logada (sem regressão) |
| Manual | Navegar logado e anônimo por todas as telas, em desktop e mobile (sidebar colapsável), e conferir os temas claro e escuro |

## Riscos
- **Shell dinâmico na consulta:** trocar de layout persistente entre visitas faz o React remontar o shell. É aceitável, porque só acontece ao entrar ou sair da consulta.
- **Contagem × filtro:** o indicador "Em análise" conta só `EmAnalise`. O filtro usa o mesmo valor exato, então número e lista batem.
