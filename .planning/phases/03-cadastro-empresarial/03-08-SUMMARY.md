---
phase: 03-cadastro-empresarial
plan: 08
subsystem: ui
tags: [react, inertia, portal, companies, cnae, confirm-dialog, useHttp, debounce]

requires:
  - phase: 03-cadastro-empresarial
    plan: 05
    provides: shape das props de CompanyController::show (company, cnaes, links, abilities) e DELETE empresas/{company}/vinculo com ended_reason
  - phase: 03-cadastro-empresarial
    plan: 06
    provides: PUT cnae-principal {cnae_id}, PUT cnaes-secundarios {cnaes[]}, GET /portal/cnaes?search= (só ativos, máx. 20, shape id/formatted_code/description)
  - phase: 02.4-template-listagens
    provides: Card/CardHeader/CardContent, ConfirmDialog, Alert, Badge, Button, Table, PageHeader, Skeleton
provides:
  - Página portal/empresas/detalhe (HU-024/025/026/028 na UI) com seções Dados / CNAEs / Vínculos
  - CnaePicker local às páginas de empresas (busca incremental real em GET /portal/cnaes com debounce)
  - Fluxos de confirmação ConfirmDialog warning (troca de principal) e danger (encerramento de vínculo com motivo)
affects: [03-09 (full-suite + screenshots da fase), fase-7-consultas (navegação a partir da empresa), fase-8-solicitacoes (CNAE principal da empresa)]

tech-stack:
  added: []
  patterns:
    - "Busca incremental standalone via useHttp com termo na URL (data vazio) + guarda de sequência (requestSeq) contra respostas fora de ordem"
    - "Estado local de conjunto (secundários) re-sincronizado com as props do servidor por chave de ids ordenados (serverIdsKey) — sobrevive a mudanças cruzadas (troca de principal demove para os secundários)"
    - "Input de formulário dentro do ConfirmDialog via description ReactNode com wrapper text-start (motivo opcional do encerramento)"
    - "Datas YYYY-MM-DD formatadas por split manual (sem Date) — evita off-by-one de fuso em GMT-3"

key-files:
  created:
    - resources/js/pages/portal/empresas/detalhe.tsx
    - resources/js/pages/portal/empresas/cnae-picker.tsx
  modified: []

key-decisions:
  - "Termo de busca do picker vai na URL (encodeURIComponent) com data {} no useHttp — evita race do setData assíncrono; mergeDataIntoQueryString não toca a URL com data vazio (verificado no core do Inertia)"
  - "Respostas fora de ordem ignoradas por sequência (requestSeq) em vez de cancel() — não depende da semântica de cancelamento do hook"
  - "Picker do principal NÃO exclui os secundários (promover secundário a principal é caso legítimo — setPrimary demove/promove no service [03-06]); exclui apenas o principal atual"
  - "Empty states curtos (borda dashed + texto) em vez do componente EmptyState (py-12 alto demais para blocos internos de card)"
  - "Botão 'Encerrar meu vínculo' com Button variant=danger no header do card de vínculos (ação destrutiva explícita; confirmação danger na sequência)"

patterns-established:
  - "Picker de domínio local às páginas (pages/portal/empresas/cnae-picker.tsx) — não entra em components/ui sem segunda utilização (coordenação 2.4)"

requirements-completed: [HU-024, HU-025, HU-026, HU-028]

duration: 11min
completed: 2026-06-12
---

# Phase 3 Plan 08: Tela de Detalhe da Empresa (HU-024/025/026/028) Summary

**Página de detalhe da empresa no portal com edição de dados (CNPJ imutável comunicado), gestão de CNAE principal/secundários com busca server-side real (GET /portal/cnaes via useHttp com debounce 350ms) e confirmações ConfirmDialog, e encerramento do próprio vínculo com motivo opcional dentro do diálogo danger — erros do backend (cnae_id/cnaes/vinculo) comunicados em Alerts nos cards.**

## Performance

- **Duration:** ~11 min
- **Started:** 2026-06-12T20:02:44Z
- **Completed:** 2026-06-12T20:13:07Z
- **Tasks:** 2
- **Files modified:** 2 (2 criados)

## Accomplishments

### Task 1 — Seção Dados com CNPJ imutável (HU-024)
- `detalhe.tsx` com tipos locais espelhando o contrato exato de `CompanyController::show` ([03-05]): `CompanyDetail`, `LinkRow`, `Abilities`, `CompanyCnaes` — `types/index.d.ts` intocado.
- PageHeader com título = razão social, trilha [Meu painel, Minhas empresas] e badges no cabeçalho: origem (light manual / info REDESIM, com `title` da data de sincronização quando `redesim_synced_at` presente) e situação do vínculo do usuário (Ativo/Encerrado).
- Card "Dados da empresa": `useForm` com os 15 campos editáveis; CNPJ em `Input disabled` com `company.formatted_cnpj` e hint "O CNPJ não pode ser alterado." — FORA do useForm (backend já o ignora por omissão nas rules, padrão CPF [01-08]).
- Grid responsivo `sm:grid-cols-2` com seções internas (Identificação/Endereço/Contato), erro+hint por campo, `form.put` com `preserveScroll`; botão "Salvar alterações" `disabled={processing || !abilities.update}`.
- Vínculo encerrado (`!abilities.update`): todos os inputs `disabled` + Alert warning "Seu vínculo com esta empresa está encerrado — os dados são somente leitura."

### Task 2 — CNAEs com picker real + Vínculos com encerramento (HU-025/026/028)
- **CnaePicker** (`pages/portal/empresas/cnae-picker.tsx`, local — NÃO em components/ui): busca incremental com debounce 350ms e mínimo 2 caracteres; somente dados reais de `GET /portal/cnaes?search=`; estados de carregando (Skeleton), vazio ("Nenhum CNAE ativo encontrado.") e erro (Alert inline); `excludeIds` filtra resultados; seleção limpa o campo. Exporta o tipo `CnaeOption` consumido pelo detalhe.
- **Card "Atividades econômicas (CNAEs)"** (controles só com `abilities.manageCnaes`; senão somente leitura):
  - Principal: bloco com código+descrição (ou empty state curto); "Definir/Alterar principal" abre o picker; seleção abre ConfirmDialog **warning** "Alterar CNAE principal" com o impacto no enquadramento; confirmar → `router.put cnae-principal {cnae_id}` com processing.
  - Secundários: linhas com botão remover sobre estado local; picker adiciona (excludeIds = principal + selecionados); badge warning "Alterações não salvas" quando o conjunto difere do servidor; "Salvar secundários" → `router.put cnaes-secundarios {cnaes: ids}` (conjunto exato; `[]` remove todos).
  - Erros do backend (`errors.cnae_id`, `errors.cnaes`, `errors['cnaes.N']`) coletados e exibidos em Alert error dentro do card.
- **Card "Vínculos"**: tabela Usuário/Papel/Início/Fim/Motivo com destaque da linha do próprio usuário (fundo brand + badge "Você"); "Encerrar meu vínculo" (visível com `abilities.endLink`) abre ConfirmDialog **danger** com aviso de preservação do histórico + `Input` de motivo opcional dentro da description; confirmar → `router.delete vinculo` com `ended_reason`; `errors.vinculo` (último responsável) em Alert error no topo do card.

## API do CnaePicker

```tsx
interface CnaePickerProps {
    onSelect: (cnae: CnaeOption) => void; // CnaeOption = { id, formatted_code, description }
    excludeIds?: number[];                // ocultados dos resultados (principal atual, já selecionados)
    placeholder?: string;
}
```

- Request standalone: `useHttp<Record<string, never>, CnaeOption[]>({})` + `get('/portal/cnaes?search=' + encodeURIComponent(term))` — termo na URL com data vazio (o `mergeDataIntoQueryString` do core não altera a URL quando `data` é vazio; evita race do `setData` assíncrono).
- Concorrência: contador `requestSeq` — respostas de buscas antigas são descartadas em `onSuccess`/`onError`.
- Debounce 350ms (mesma constante do use-server-table), mínimo 2 caracteres; abaixo disso a lista é limpa sem request.

## Fluxos de confirmação

| Ação | Variant | Conteúdo | Request |
|---|---|---|---|
| Trocar CNAE principal | warning | impacto no enquadramento + código/descrição do novo | `PUT /portal/empresas/{id}/cnae-principal {cnae_id}` |
| Encerrar meu vínculo | danger | aviso de histórico preservado + Input "Motivo (opcional)" | `DELETE /portal/empresas/{id}/vinculo {ended_reason}` |

Ambos com `processing` no diálogo, `preserveScroll`, fechamento no `onFinish` e flash `status` exibido pelo PortalLayout.

## Task Commits

1. **Task 1: seção Dados com CNPJ imutável (HU-024)** - `0e4999f` (feat)
2. **Task 2: CNAEs com picker real + vínculos com encerramento (HU-025/026/028)** - `046808c` (feat)

## Files Created/Modified
- `resources/js/pages/portal/empresas/detalhe.tsx` - página de detalhe com seções Dados / CNAEs / Vínculos, confirmações e erros comunicados
- `resources/js/pages/portal/empresas/cnae-picker.tsx` - busca incremental de CNAEs ativos (endpoint real, debounce, estados)

## Decisions Made
- Termo de busca na URL + data vazio no useHttp (anti-race) com guarda de sequência em vez de `cancel()`.
- Picker do principal exclui só o principal atual — secundário pode ser promovido (o service demove/promove preservando a linha do pivot [03-06]).
- Empty states internos curtos (borda dashed) em vez do componente EmptyState (py-12 desproporcional dentro de card com header próprio).
- Datas `YYYY-MM-DD` formatadas por split manual — `new Date('YYYY-MM-DD')` desloca um dia em GMT-3 (bug evitado nesta página; padrão recomendado para telas futuras).
- Layout persistente do Inertia (`Page.layout = ...`) nas duas páginas, padrão do commit 7dd1ab0.

## Deviations from Plan

None - plan executed exactly as written.

## Issues Encountered
- Nenhum. Todos os componentes 2.4 previstos existiam no disco (Card, ConfirmDialog, Alert, Badge, Table, PageHeader, Skeleton) — nenhum fallback 2.1/2.2 foi necessário.

## User Setup Required
None - nenhuma configuração de serviço externo.

## Next Phase Readiness
- HU-024/025/026/028 navegáveis de ponta a ponta no detalhe contra os endpoints reais ([03-05]/[03-06]) com confirmações e mensagens pt-BR.
- **03-09:** full-suite + screenshots da fase; a pendência [03-04] de adicionar `->component()` nos testes Inertia agora pode ser fechada também para `portal/empresas/detalhe` (página existe no disco).
- Territórios respeitados: nenhum arquivo de `components/ui/*`, `pages/gestao/*` ou do plano paralelo 03-07 (index/cadastrar/portal-layout) foi tocado.

## Verification
- `npm run typecheck` e `npm run build` — verdes.
- `php artisan test --compact --filter='CompanyUpdateTest|PrimaryCnaeTest|SecondaryCnaesTest|EndCompanyLinkTest'` — 32/32 verdes.
- `php artisan test --compact tests/Feature/Companies` — 83/83 verdes (regressão escopada da wave; full-suite no 03-09).
- Greps de aceitação: `formatted_cnpj` (Input disabled), "O CNPJ não pode ser alterado.", `abilities` controlando edição, `/portal/cnaes` no picker, `ConfirmDialog` (warning e danger), `ended_reason` no delete, `errors.vinculo` comunicado — todos presentes.
- `git status` durante a execução: apenas detalhe.tsx e cnae-picker.tsx alterados.

---
*Phase: 03-cadastro-empresarial*
*Completed: 2026-06-12*
