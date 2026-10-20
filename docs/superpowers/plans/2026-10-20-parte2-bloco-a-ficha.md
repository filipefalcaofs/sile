# Parte 2 — Bloco A (Ficha de Análise: limpeza e layout) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** a ficha fica mais curta e mostra só o que a analista usa. Ela ganha o destaque no Salvar, o parecer ampliável, sugestões de condicionante e precedentes clicáveis em nova aba. O contorno do polígono passa a aparecer.

**Architecture:** a maior parte é edição de `resources/js/pages/gestao/ficha-analise/show.tsx`. A lógica testável sai para funções puras com Vitest:
- desfecho da síntese;
- centro da geometria;
- sugestões de condicionante.

Os precedentes mudam o contrato do repositório para trazer o BAP e excluir o próprio processo.

**Tech Stack:** React 19, Inertia v3, react-leaflet, Tailwind 4, Vitest + happy-dom (sem Testing Library; padrão `createRoot`/`act`), Laravel 13, PHPUnit 12.

**Spec:** `docs/superpowers/specs/2026-10-20-ficha-analise-layout-parte-2-design.md`, Bloco A.

## Global Constraints

- Arquivo `show.tsx` tem WIP alheio de renumeração de HU em comentários: sempre `git add -p`.
- Rótulos fixos do cliente: "Deferido" / "Indeferido" (item 11), "Encaminhar para Chefia" é do Bloco C (não mexer aqui), "Salvar Ficha" laranja (`--color-orange-500` `#fb6514`, hover `--color-orange-600` `#ec4a0a`, ambos já em `resources/css/app.css:99-100`).
- Dados do motor (`motivo_fora_do_expresso`, risco, fundamentação) continuam no backend: só saem da tela.
- Verificação de tela: `npm run typecheck`, `npm run build` e conferência no navegador do app com o servidor de desenvolvimento.
- Sem coautoria/marca de IA.

## Review Focus

- Processo `cancelada`/`desativado`/`cassado`/`revogado`: a síntese não aparece (só deferida/indeferida mostram desfecho) — Task 2.
- GeoJSON `Feature` envolvendo Polygon (formato possível do `property_polygon_geojson`): o centro e o enquadramento continuam funcionando — Task 3.
- Termo de condicionante com acento/maiúscula diferente do texto da biblioteca ("area" × "Área") ainda sugere — Task 6.
- Texto da biblioteca já adicionado às condicionantes não aparece de novo nas sugestões — Task 6.
- Precedente sem BAP (processo não veio da JUCEB): link mostra o VIA no lugar, sem "null" — Task 7.

---

### Task 1: Remover quadro do expresso, confiança da IA e card de risco (itens 7, 10, 13)

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — 1918-1926 (item 7); 3280-3281 e 3383-3387 e `confiancaLabel` 3307-3322 (item 10); 2081-2206 (item 13) e helpers `riscoRank` (~600) e `rotuloRiscoSanitario` (~625).

- [ ] **Step 1: Remover**

Apagar o bloco `item.motivo_fora_do_expresso && (...)`; tirar ` · confiança …` do `<summary>` da minuta e o `<Badge>` "Confiança:" do card de justificativa; apagar o card inteiro "Classificação de Risco e Fundamentação Legal" (do comentário em 2081 até o `)}` em 2206). Apagar `confiancaLabel`, `riscoRank` e `rotuloRiscoSanitario` se o `grep -n` mostrar zero usos restantes. Não apagar `formatarMoeda` (usado em 2058). Remover imports que ficarem sem uso.

- [ ] **Step 2: Conferir**

Run: `grep -n "Por que não seguiu o fluxo expresso\|confiança\|Confiança:\|Classificação de Risco" resources/js/pages/gestao/ficha-analise/show.tsx; npm run typecheck && npm run build`
Expected: grep sem resultado; typecheck e build sem erro.

- [ ] **Step 3: Commit**

```bash
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: ficha deixa de exibir o motivo fora do expresso, a confiança da IA e o card de risco e fundamentação"
```

### Task 2: Síntese só com Deferido/Indeferido na análise concluída (item 11)

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/sintese.ts` (reescrever)
- Modify: `resources/js/pages/gestao/ficha-analise/sintese.test.ts` (reescrever)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — import L15, `useMemo` 809-821, call site 1622, `SinteseLeitura` 3015-3056 (e `marcaLeitura` se ficar sem uso)

**Interfaces:**
- Produces: `export type DesfechoAnalise = { titulo: 'Deferido' | 'Indeferido'; tom: 'deferida' | 'indeferida' }` e `export function desfechoDaAnalise(statusProcesso: string): DesfechoAnalise | null` — `'deferida'` → Deferido, `'indeferida'` → Indeferido, qualquer outro → `null`. `sinteseDaFicha` e os tipos antigos deixam de existir.

- [ ] **Step 1: Reescrever os testes**

```ts
it('processo deferido mostra Deferido', () => expect(desfechoDaAnalise('deferida')).toEqual({ titulo: 'Deferido', tom: 'deferida' }));
it('processo indeferido mostra Indeferido', () => expect(desfechoDaAnalise('indeferida')).toEqual({ titulo: 'Indeferido', tom: 'indeferida' }));
it.each(['em_analise', 'em_pendencia', 'cassado', 'desativado', 'revogado', 'cancelada'])('%s não mostra desfecho', (s) => expect(desfechoDaAnalise(s)).toBeNull());
```

- [ ] **Step 2: Ver falhar**

Run: `npx vitest run resources/js/pages/gestao/ficha-analise/sintese.test.ts`
Expected: FAIL (`desfechoDaAnalise` não existe).

- [ ] **Step 3: Implementar** — `sintese.ts` só com a função acima. Em `show.tsx`: `const desfecho = desfechoDaAnalise(processo.status)`; `SinteseLeitura` vira `DesfechoAnalise({ desfecho })` renderizando só o título no mesmo `Card` com a mesma cor por `tom` (sem lista, sem botão "Ver o parecer completo"); call site `{desfecho && <DesfechoAnaliseCard desfecho={desfecho} />}`. Remover o `useMemo` antigo e o que ficar sem uso.

- [ ] **Step 4: Ver passar**

Run: `npx vitest run resources/js/pages/gestao/ficha-analise && npm run typecheck`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/sintese.ts resources/js/pages/gestao/ficha-analise/sintese.test.ts
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: topo da ficha mostra só Deferido ou Indeferido quando a análise está concluída"
```

### Task 3: Contorno do polígono visível e camadas abaixo do mapa (itens 2 e 18)

**Files:**
- Modify: `resources/js/components/geo/geometria.ts` (`centroDaGeometria`, L11) + `geometria.test.ts`
- Modify: `resources/js/components/geo/map-imovel.tsx`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — `centroideDoPoligono` 440-471 (remover), uso 1522-1523, layout 1627-1725, descrição do card da esquerda (L1631)

**Interfaces:**
- Produces: `centroDaGeometria` passa a aceitar também `Feature` (usa `geometry`), mantendo a assinatura `(geometria: GeoJsonObject | null | undefined) => { lat: number; lng: number } | null`.
- Produces: `MapImovelProps.enquadrar?: boolean` (padrão `false`). Quando `true` e há `camadas`, um componente interno `EnquadrarCamadas` faz, ao montar e quando as camadas mudam, `map.fitBounds(L.geoJSON(camadas.map(c => c.geojson)).getBounds(), { padding: [40, 40], maxZoom: 19 })` (só se os bounds forem válidos). Cada `<GeoJSON>` ganha `key={\`${c.id}:${chaveDaGeometria(c.geojson)}\`}` com `chaveDaGeometria(g: GeoJsonObject): string` exportada de `geometria.ts` (hash curto e estável de `JSON.stringify(g)`, ex.: djb2 em hex).

- [ ] **Step 1: Testes em `geometria.test.ts`**

- `'centro de Feature com Polygon usa a geometria'`: `{ type: 'Feature', properties: {}, geometry: { type: 'Polygon', coordinates: [[[0,0],[2,0],[2,2],[0,2],[0,0]]] } }` → `{ lat: 1, lng: 1 }`.
- `'centro de MultiPolygon usa o primeiro anel'`: já coberto? se não houver, `{ type: 'MultiPolygon', coordinates: [[[[0,0],[2,0],[2,2],[0,2],[0,0]]]] }` → `{ lat: 1, lng: 1 }`.
- `'chave da geometria muda quando as coordenadas mudam'`: `chaveDaGeometria(a) !== chaveDaGeometria(b)` e `chaveDaGeometria(a) === chaveDaGeometria(structuredClone(a))`.

- [ ] **Step 2: Ver falhar**

Run: `npx vitest run resources/js/components/geo/geometria.test.ts`
Expected: FAIL (Feature e `chaveDaGeometria`).

- [ ] **Step 3: Implementar** `geometria.ts`, `map-imovel.tsx`; em `show.tsx` remover `centroideDoPoligono` e usar `centroDaGeometria(localizacao?.poligono) ?? centroDaGeometria(localizacao?.poligono_enderecamento)`, passar `enquadrar` ao `MapaSection` da ficha. Mover o bloco `localizacao?.camadas && (...)` (1655-1665) para dentro do card Polígono, logo depois do `<dl>` de Zona/Via e do aviso de pendência, mantendo título/descrição "Camadas do imóvel"; ajustar a descrição do card da esquerda para não citar camadas.

- [ ] **Step 4: Ver passar**

Run: `npx vitest run resources/js/components/geo && npm run typecheck && npm run build`
Expected: PASS.

- [ ] **Step 5: Conferir na tela** — abrir a ficha de um processo com polígono na base local (`select id from viability_requests where property_polygon_geojson is not null limit 5` via `php artisan tinker --execute` só leitura) e verificar o contorno sob o marcador e as camadas abaixo do mapa. Sem processo com polígono na base local: registrar na entrega que a confirmação fica com a SEDUR na homologação.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/geo/geometria.ts resources/js/components/geo/geometria.test.ts resources/js/components/geo/map-imovel.tsx
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: mapa da ficha enquadra o polígono do imóvel e as camadas ficam abaixo dele"
```

### Task 4: "Salvar Ficha" em laranja (item 14a)

**Files:**
- Modify: `resources/js/components/ui/button.tsx` (`variant` e `variantClasses`)
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` (~2527)

**Interfaces:**
- Produces: `variant` aceita `'destaque'` → `'bg-orange-500 text-white shadow-theme-xs hover:bg-orange-600 disabled:opacity-50 focus-visible:outline-orange-500'`.

- [ ] **Step 1: Implementar** — nova variante; "Salvar Ficha" com `variant="destaque"`.
- [ ] **Step 2: Conferir** — `npm run typecheck && npm run build`; na tela, botão laranja com texto branco, legível no tema escuro.
- [ ] **Step 3: Commit**

```bash
git add resources/js/components/ui/button.tsx
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "fix: botão Salvar Ficha em laranja para destacar"
```

### Task 5: Parecer técnico ampliável (item 17)

**Files:**
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — `Textarea` local 635-679, parecer 2440-2467, um `Modal` novo

**Interfaces:**
- Produces: `Textarea` local ganha `ampliavel?: boolean` (padrão `false`). Com `true`: sem teto de 160px no auto-ajuste (teto 480px), classe `resize-y` no lugar de `resize-none`, sem `max-h-40`. Demais usos (convite, vistoria) ficam como estão.
- Produces: botão `"Expandir"` (`size="xs" variant="ghost"`) nas `actions` do card Parecer técnico, junto de "Inserir texto-padrão"; abre `<Modal isOpen onClose ariaLabel="Parecer técnico" className="m-4 max-h-[90vh] w-full max-w-[1000px] overflow-y-auto p-6 lg:p-8">` com um `<textarea>` de `rows={24}`, mesmo `value={parecer}`/`onChange={setParecer}`, `maxLength={2000}`, `disabled={!editavel}` e botão "Fechar".

- [ ] **Step 1: Implementar** — usar `ampliavel` no `#parecer-editor`; manter `id="parecer-editor"` no campo da ficha (alvo de scroll em ~1376).
- [ ] **Step 2: Conferir** — `npm run typecheck && npm run build`; na tela: arrastar o canto do parecer aumenta a altura; "Expandir" abre o modal com o mesmo texto e o que é digitado lá aparece no campo ao fechar.
- [ ] **Step 3: Commit**

```bash
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: parecer técnico da ficha pode ser ampliado e aberto em tela grande"
```

### Task 6: Sugestões da biblioteca ao digitar a condicionante (item 16)

**Files:**
- Create: `resources/js/pages/gestao/ficha-analise/sugestoes-condicionante.ts`
- Create: `resources/js/pages/gestao/ficha-analise/sugestoes-condicionante.test.ts`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — card Condicionantes 2260-2315 (Input 2278-2291, texto 2269-2270)

**Interfaces:**
- Produces: `export function sugerirCondicionantes(textos: { id: number; category: string; content: string }[], termo: string, jaAdicionadas: string[], limite = 5): { id: number; content: string }[]`:
  - termo normalizado (trim, minúsculas, sem acento via `normalize('NFD').replace(/\p{Diacritic}/gu, '')`) com menos de 3 letras → `[]`;
  - casa quando **todas** as palavras do termo aparecem no conteúdo normalizado;
  - exclui conteúdos já em `jaAdicionadas` (comparação por `trim()`);
  - textos de `category === 'condicionante'` vêm antes dos demais; dentro de cada grupo, a ordem original;
  - corta em `limite`.

- [ ] **Step 1: Testes**

- `'menos de 3 letras não sugere'`: `sugerirCondicionantes(textos, 'de', [])` → `[]`.
- `'ignora acento e maiúscula'`: texto "Desde que a Área utilizada…", termo `'area util'` → contém esse id.
- `'condicionantes vêm antes dos demais'`: um texto `deferimento` e um `condicionante` com "escritório" → `[0].id` é o de condicionante.
- `'não repete o que já foi adicionado'`.
- `'respeita o limite'`: 7 que casam → 5.

- [ ] **Step 2: Ver falhar**

Run: `npx vitest run resources/js/pages/gestao/ficha-analise/sugestoes-condicionante.test.ts`
Expected: FAIL (módulo não existe).

- [ ] **Step 3: Implementar a função e a lista na tela**

Combobox acessível no `Input` `#nova-condicao`: `role="combobox"`, `aria-expanded`, `aria-controls="sugestoes-condicao"`, `aria-activedescendant`; lista `<ul id="sugestoes-condicao" role="listbox">` logo abaixo do campo com `role="option"` por item; setas ↑/↓ movem o destaque, Enter com destaque chama `adicionarCondicao(conteudo)` e limpa o campo (Enter sem destaque mantém o comportamento atual de adicionar o texto livre), Esc fecha; clique numa sugestão também adiciona. Trocar o texto de 2269-2270 para: "O texto livre e a biblioteca servem só para acrescentar uma obrigação que o motor não sugeriu. Ao digitar, aparecem sugestões da biblioteca."

- [ ] **Step 4: Ver passar**

Run: `npx vitest run resources/js/pages/gestao/ficha-analise && npm run typecheck && npm run build`
Expected: PASS. Na tela: digitar "defe" mostra sugestões; teclado funciona.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/gestao/ficha-analise/sugestoes-condicionante.ts resources/js/pages/gestao/ficha-analise/sugestoes-condicionante.test.ts
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: campo de condicionante sugere textos da biblioteca enquanto se digita"
```

### Task 7: Precedentes com BAP e abertura em nova aba (item 9)

**Files:**
- Modify: `app/Services/Analise/PrecedentRepository.php` (contrato + PHPDoc)
- Modify: `app/Services/Analise/PostgisPrecedentRepository.php` (`propertyPrecedents`)
- Modify: `tests/Support/Analise/FakePrecedentRepository.php`
- Modify: `app/Services/Analise/PrecedentService.php:31-55`
- Modify: `resources/js/pages/gestao/ficha-analise/show.tsx` — `PrecedenteImovel` 155-162, `PrecedentesPanel` 3698-3727
- Test: `tests/Feature/Analise/PrecedentServiceTest.php`, `tests/Feature/Analise/AnalisePrecedentesPostgisTest.php`

**Interfaces:**
- Produces: `propertyPrecedents(array $currentGeojson, int $limit, ?string $fallbackStreet = null, ?string $fallbackNumber = null, ?int $exceptRequestId = null): array` — cada linha ganha `bap: ?string` (`vr.external_reference`); com `$exceptRequestId`, `where('vr.id', '!=', $exceptRequestId)`.
- Fake: registra `except_request_id` em `$propertyCalls` e inclui `bap` no shape documentado.
- `PrecedentService::forRecord` passa `exceptRequestId: $request?->id`.
- TS: `PrecedenteImovel` ganha `bap: string | null`.

- [ ] **Step 1: Testes**

- `PrecedentServiceTest::test_precedentes_trazem_o_bap_e_excluem_o_proprio_processo`: fake com uma linha `['viability_request_id' => 9, 'protocol_number' => 'VIA-2026-000009', 'bap' => 'BAP2601853153', 'outcome' => 'indeferida', ...]`; resultado `imovel[0]['bap'] === 'BAP2601853153'` e `$fake->propertyCalls[0]['except_request_id'] === $request->id`.
- `AnalisePrecedentesPostgisTest` (segue o skip de PostGIS do arquivo): novo caso — processo atual decidido no mesmo polígono não aparece; outro processo decidido com `external_reference` aparece com `bap` preenchido.

- [ ] **Step 2: Ver falhar**

Run: `php artisan test --compact tests/Feature/Analise/PrecedentServiceTest.php tests/Feature/Analise/AnalisePrecedentesPostgisTest.php`
Expected: FAIL nos novos.

- [ ] **Step 3: Implementar backend e tela** — na tela, cada decidido vira item no mesmo formato de `em_analise_processos` (3676-3694): `<a href={\`/gestao/processos/${id}\`} target="_blank" rel="noopener noreferrer">` com `bap ?? protocol_number ?? #id` em destaque e o `protocol_number` ao lado em cinza (só quando há BAP), mais a marca pequena colorida do resultado (`Badge` atual com `statusColor`/`statusLabel`, `size="sm"`). Sai a linha "serviço · analista · data". Agrupar visualmente: "Em análise neste imóvel" (já existe) e, abaixo, "Decididos neste imóvel".

- [ ] **Step 4: Ver passar**

Run: `php artisan test --compact --filter=Precedent && npm run typecheck`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Analise/PrecedentRepository.php app/Services/Analise/PostgisPrecedentRepository.php app/Services/Analise/PrecedentService.php tests/Support/Analise/FakePrecedentRepository.php tests/Feature/Analise/PrecedentServiceTest.php tests/Feature/Analise/AnalisePrecedentesPostgisTest.php
git add -p resources/js/pages/gestao/ficha-analise/show.tsx
git commit -m "feat: precedentes do imóvel mostram BAP e VIA e abrem em nova aba"
```

### Task 8: Fechamento do bloco

- [ ] **Step 1: Suíte das áreas tocadas**

Run: `npm test && npm run typecheck && npm run build && php artisan test --compact tests/Feature/Analise`
Expected: PASS (falhas que já existiam no commit `6e232c1b` são anotadas na entrega).
