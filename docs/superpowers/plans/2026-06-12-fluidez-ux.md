# Fluidez de UX — Plano de Implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Motion consistente em todo o frontend (tokens centrais + reduced-motion), progresso de navegação Inertia, animações de entrada/saída nos componentes globais e `DataTable` adaptativo (cards em mobile, tabela em desktop).

**Architecture:** Camada de motion 100% CSS/Tailwind v4 (`@theme` + keyframes + utilities) com bloco global `prefers-reduced-motion`; lógica client-side nova (hook de exit, derivação de layout mobile) coberta por vitest; `DataTable` ganha modo card derivado das mesmas `ColumnDef`.

**Tech Stack:** Tailwind CSS 4, React 19, Inertia v3, vitest + @testing-library/react (novo, aprovado pelo usuário).

**Spec:** `docs/superpowers/specs/2026-06-12-fluidez-ux-design.md`

**Contexto de execução:** trabalhar no workspace atual (sem worktree — o repo tem mudanças pendentes de outra frente e o dev server `composer run dev` roda aqui). Commits atômicos por task, somente dos arquivos da task.

---

### Task 1: Infraestrutura de testes JS (vitest)

**Files:**
- Modify: `package.json`
- Create: `vitest.config.ts`
- Create: `tests/js/setup.ts`
- Create: `tests/js/smoke.test.tsx`

- [ ] **Step 1: Instalar devDependencies**

```bash
npm install -D vitest @testing-library/react @testing-library/jest-dom jsdom
```

Expected: instala sem erros (versões latest).

- [ ] **Step 2: Criar `vitest.config.ts`**

Config separada do `vite.config.js` para não carregar `laravel-vite-plugin` e `@inertiajs/vite` no contexto de teste:

```ts
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitest/config';

export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./tests/js/setup.ts'],
        include: ['tests/js/**/*.test.{ts,tsx}'],
    },
});
```

- [ ] **Step 3: Criar `tests/js/setup.ts`**

```ts
import '@testing-library/jest-dom/vitest';
```

- [ ] **Step 4: Adicionar script de teste ao `package.json`**

Em `scripts`, depois de `"typecheck"`:

```json
"test": "vitest run"
```

- [ ] **Step 5: Criar smoke test `tests/js/smoke.test.tsx`**

```tsx
import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

describe('infraestrutura de testes', () => {
    it('renderiza componente React no jsdom', () => {
        render(<p>infra ok</p>);
        expect(screen.getByText('infra ok')).toBeInTheDocument();
    });
});
```

- [ ] **Step 6: Rodar e verificar**

Run: `npm test`
Expected: 1 passed.

- [ ] **Step 7: Commit**

```bash
git add package.json package-lock.json vitest.config.ts tests/js/
git commit -m "test: adiciona vitest e testing-library para testes de frontend"
```

---

### Task 2: Tokens de motion, keyframes e reduced-motion no CSS

**Files:**
- Modify: `resources/css/app.css`

Alteração puramente CSS (sem lógica) — verificação por build.

- [ ] **Step 1: Adicionar tokens e animações ao `@theme`**

Dentro do bloco `@theme` existente, após `--shadow-focus-ring` (linha ~157), adicionar:

```css
    /* Motion — tokens centrais de fluidez. Toda animação/transição nova
       usa estes tokens; nada de durações ou easings improvisados. */
    --ease-fluid: cubic-bezier(0.22, 1, 0.36, 1);
    --ease-snappy: cubic-bezier(0.2, 0, 0, 1);

    --animate-fade-in: fade-in 250ms var(--ease-fluid) backwards;
    --animate-fade-in-up: fade-in-up 250ms var(--ease-fluid) backwards;
    --animate-fade-in-down: fade-in-down 250ms var(--ease-fluid) backwards;
    --animate-scale-in: scale-in 200ms var(--ease-fluid) backwards;
    --animate-fade-out: fade-out 200ms var(--ease-snappy) forwards;
    --animate-scale-out: scale-out 200ms var(--ease-snappy) forwards;

    @keyframes fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    @keyframes fade-in-up {
        from { opacity: 0; transform: translateY(8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes fade-in-down {
        from { opacity: 0; transform: translateY(-8px); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes scale-in {
        from { opacity: 0; transform: scale(0.95); }
        to { opacity: 1; transform: scale(1); }
    }
    @keyframes fade-out {
        from { opacity: 1; }
        to { opacity: 0; }
    }
    @keyframes scale-out {
        from { opacity: 1; transform: scale(1); }
        to { opacity: 0; transform: scale(0.95); }
    }
```

Nota: `backwards` nas entradas é obrigatório — elementos com `animation-delay` (stagger) ficam no estado inicial (invisíveis) durante o delay, sem flash.

- [ ] **Step 2: Adicionar bloco global de reduced-motion no fim do arquivo**

```css
/* Acessibilidade — usuários que pedem movimento reduzido (eMAG).
   Zera animações e transições globalmente; spinners/skeletons ficam
   estáticos, e a comunicação de carregamento permanece via aria-busy
   e textos. Scroll programático é tratado em JS (prefersReducedMotion). */
@media (prefers-reduced-motion: reduce) {
    *,
    ::before,
    ::after {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
        scroll-behavior: auto !important;
    }
}
```

- [ ] **Step 3: Verificar build**

Run: `npm run build`
Expected: build conclui sem erros.

- [ ] **Step 4: Commit**

```bash
git add resources/css/app.css
git commit -m "feat: adiciona tokens de motion e reduced-motion globais"
```

---

### Task 3: Helper `prefersReducedMotion` (TDD)

**Files:**
- Create: `resources/js/lib/motion.ts`
- Test: `tests/js/lib/motion.test.ts`

- [ ] **Step 1: Escrever teste que falha**

`tests/js/lib/motion.test.ts`:

```ts
import { afterEach, describe, expect, it, vi } from 'vitest';
import { prefersReducedMotion } from '@/lib/motion';

function mockMatchMedia(matches: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockReturnValue({ matches }),
    );
}

describe('prefersReducedMotion', () => {
    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('retorna true quando o usuário pede movimento reduzido', () => {
        mockMatchMedia(true);
        expect(prefersReducedMotion()).toBe(true);
    });

    it('retorna false quando não há preferência de movimento reduzido', () => {
        mockMatchMedia(false);
        expect(prefersReducedMotion()).toBe(false);
    });
});
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `npm test -- tests/js/lib/motion.test.ts`
Expected: FAIL — módulo `@/lib/motion` não existe.

- [ ] **Step 3: Implementar `resources/js/lib/motion.ts`**

```ts
/**
 * Indica se o usuário pediu movimento reduzido (prefers-reduced-motion).
 * Usar em animações programáticas (ex.: scrollIntoView smooth), que o
 * bloco CSS global não cobre.
 */
export function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}
```

- [ ] **Step 4: Rodar e verificar que passa**

Run: `npm test -- tests/js/lib/motion.test.ts`
Expected: 2 passed.

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/motion.ts tests/js/lib/motion.test.ts
git commit -m "feat: adiciona helper prefersReducedMotion"
```

---

### Task 4: Progresso de navegação Inertia + entrada de página nos layouts

**Files:**
- Modify: `resources/js/app.tsx`
- Modify: `resources/js/components/app/app-shell.tsx`
- Modify: `resources/js/layouts/auth-layout.tsx`

Antes de implementar, confirmar a opção `progress` do Inertia v3 com `search-docs` (Boost): queries `["progress indicator", "createInertiaApp options"]`.

- [ ] **Step 1: Configurar progress no `resources/js/app.tsx`**

```tsx
import { createInertiaApp } from '@inertiajs/react';

createInertiaApp({
    strictMode: true,
    progress: {
        color: '#1351b4',
        delay: 250,
        showSpinner: false,
    },
});
```

(Ajustar à API confirmada pelo search-docs; `color` deve ser o brand-500.)

- [ ] **Step 2: Fade-in de conteúdo por navegação no `AppShell`**

Em `resources/js/components/app/app-shell.tsx`, importar `usePage` e usar o pathname como `key` do contêiner de conteúdo — remonta (e reanima) só quando muda a rota, não a query string (busca/sort/per_page usam `preserveState` e não podem remontar):

```tsx
import { usePage } from '@inertiajs/react';
```

Em `ShellContent`, antes do `return`:

```tsx
    const { url } = usePage();
    const pathname = url.split('?')[0];
```

E trocar a div de conteúdo:

```tsx
                <div
                    id="conteudo"
                    key={pathname}
                    className="p-4 mx-auto max-w-(--breakpoint-2xl) md:p-6 animate-fade-in"
                >
                    {children}
                </div>
```

- [ ] **Step 3: Entrada do card de auth**

Em `resources/js/layouts/auth-layout.tsx`, no card do formulário (div com `rounded-xl border border-gray-200 bg-white p-6...`), adicionar `animate-fade-in-up`:

```tsx
                            <div className="animate-fade-in-up rounded-xl border border-gray-200 bg-white p-6 shadow-theme-sm sm:p-8 dark:border-gray-800 dark:bg-white/[0.03]">
```

- [ ] **Step 4: Verificar**

Run: `npm run typecheck`
Expected: sem erros.

Verificação visual (dev server já roda em `composer run dev`): navegar entre páginas da gestão — barra de progresso brand no topo em navegações lentas; conteúdo surge com fade; digitar na busca de uma listagem NÃO pode piscar/perder foco.

- [ ] **Step 5: Commit**

```bash
git add resources/js/app.tsx resources/js/components/app/app-shell.tsx resources/js/layouts/auth-layout.tsx
git commit -m "feat: adiciona progresso de navegacao e entrada suave de pagina"
```

---

### Task 5: Hook `useExitTransition` + animação do Modal (TDD)

**Files:**
- Create: `resources/js/hooks/use-exit-transition.ts`
- Test: `tests/js/hooks/use-exit-transition.test.ts`
- Modify: `resources/js/components/ui/modal.tsx`

- [ ] **Step 1: Escrever teste que falha**

`tests/js/hooks/use-exit-transition.test.ts`:

```ts
import { act, renderHook } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { useExitTransition } from '@/hooks/use-exit-transition';

describe('useExitTransition', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('não renderiza quando começa fechado', () => {
        const { result } = renderHook(() => useExitTransition(false, 200));
        expect(result.current.shouldRender).toBe(false);
    });

    it('renderiza aberto com status open', () => {
        const { result } = renderHook(() => useExitTransition(true, 200));
        expect(result.current.shouldRender).toBe(true);
        expect(result.current.status).toBe('open');
    });

    it('mantém renderizado com status closing durante a saída', () => {
        const { result, rerender } = renderHook(
            ({ open }) => useExitTransition(open, 200),
            { initialProps: { open: true } },
        );

        rerender({ open: false });

        expect(result.current.shouldRender).toBe(true);
        expect(result.current.status).toBe('closing');
    });

    it('desmonta após a duração da animação de saída', () => {
        const { result, rerender } = renderHook(
            ({ open }) => useExitTransition(open, 200),
            { initialProps: { open: true } },
        );

        rerender({ open: false });
        act(() => {
            vi.advanceTimersByTime(200);
        });

        expect(result.current.shouldRender).toBe(false);
    });

    it('reabrir durante a saída cancela o desmonte', () => {
        const { result, rerender } = renderHook(
            ({ open }) => useExitTransition(open, 200),
            { initialProps: { open: true } },
        );

        rerender({ open: false });
        act(() => {
            vi.advanceTimersByTime(100);
        });
        rerender({ open: true });
        act(() => {
            vi.advanceTimersByTime(200);
        });

        expect(result.current.shouldRender).toBe(true);
        expect(result.current.status).toBe('open');
    });
});
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `npm test -- tests/js/hooks/use-exit-transition.test.ts`
Expected: FAIL — módulo não existe.

- [ ] **Step 3: Implementar `resources/js/hooks/use-exit-transition.ts`**

```ts
import { useEffect, useRef, useState } from 'react';

interface ExitTransition {
    /** Mantém o elemento montado até a animação de saída terminar. */
    shouldRender: boolean;
    /** `closing` enquanto a animação de saída roda — aplicar classes de saída. */
    status: 'open' | 'closing';
}

/**
 * Estende a montagem de um elemento condicional pelo tempo da animação
 * de saída: ao fechar, mantém `shouldRender` por `durationMs` com
 * status `closing` para o CSS animar antes do desmonte.
 */
export function useExitTransition(isOpen: boolean, durationMs: number): ExitTransition {
    const [shouldRender, setShouldRender] = useState(isOpen);
    const previousOpen = useRef(isOpen);

    useEffect(() => {
        if (isOpen) {
            previousOpen.current = true;
            setShouldRender(true);
            return;
        }

        if (!previousOpen.current) {
            return;
        }

        previousOpen.current = false;
        const timeout = setTimeout(() => setShouldRender(false), durationMs);

        return () => clearTimeout(timeout);
    }, [isOpen, durationMs]);

    return {
        shouldRender,
        status: isOpen ? 'open' : 'closing',
    };
}
```

- [ ] **Step 4: Rodar e verificar que passa**

Run: `npm test -- tests/js/hooks/use-exit-transition.test.ts`
Expected: 5 passed.

- [ ] **Step 5: Aplicar no Modal**

Em `resources/js/components/ui/modal.tsx`:

1. Importar o hook:

```tsx
import { useExitTransition } from '@/hooks/use-exit-transition';
```

2. No corpo do componente, logo após os refs:

```tsx
    const { shouldRender, status } = useExitTransition(isOpen, 200);
```

3. Trocar `if (!isOpen) { return null; }` por:

```tsx
    if (!shouldRender) {
        return null;
    }

    const closing = status === 'closing';
```

4. No backdrop (div com `bg-gray-400/50 backdrop-blur-[32px]`), adicionar animação:

```tsx
                    className={`fixed inset-0 h-full w-full bg-gray-400/50 backdrop-blur-[32px] ${closing ? 'animate-fade-out' : 'animate-fade-in'}`}
```

5. No contêiner do diálogo (div com `ref={modalRef}`), adicionar:

```tsx
                className={`${contentClasses} ${className ?? ''} focus:outline-hidden ${closing ? 'animate-scale-out' : 'animate-scale-in'}`}
```

Os efeitos de foco/scroll já dependem de `isOpen` e continuam corretos: o foco é devolvido imediatamente ao fechar; a animação é só visual. `ConfirmDialog` herda sem mudanças.

- [ ] **Step 6: Verificar**

Run: `npm test && npm run typecheck`
Expected: todos passam.

Visual: abrir/fechar um ConfirmDialog (ex.: revogar procuração) — entrada com scale suave, saída com fade antes de sumir.

- [ ] **Step 7: Commit**

```bash
git add resources/js/hooks/use-exit-transition.ts tests/js/hooks/use-exit-transition.test.ts resources/js/components/ui/modal.tsx
git commit -m "feat: anima abertura e fechamento do modal com exit transition"
```

---

### Task 6: Dropdown, Backdrop e Alert

**Files:**
- Modify: `resources/js/components/ui/dropdown.tsx`
- Modify: `resources/js/components/app/backdrop.tsx`
- Modify: `resources/js/components/ui/alert.tsx`

Alteração puramente de classes (sem lógica).

- [ ] **Step 1: Dropdown com entrada suave**

Em `dropdown.tsx`, na div retornada, adicionar `origin-top animate-fade-in-down` (saída imediata é intencional — fechar dropdown deve ser instantâneo):

```tsx
            className={`absolute z-40 right-0 mt-2 origin-top animate-fade-in-down rounded-xl border border-gray-200 bg-white shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark ${className}`}
```

- [ ] **Step 2: Backdrop do sidebar mobile com fade**

Em `backdrop.tsx`:

```tsx
    return <div className="fixed inset-0 z-40 animate-fade-in bg-gray-900/50 lg:hidden" onClick={toggleMobileSidebar} />;
```

- [ ] **Step 3: Alert com entrada**

Em `alert.tsx`, no contêiner raiz:

```tsx
        <div className={`animate-fade-in-down rounded-xl border p-4 ${variantClasses[variant].container}`}>
```

- [ ] **Step 4: Verificar**

Run: `npm run typecheck`
Expected: sem erros. Visual: abrir dropdown do usuário no header; abrir sidebar no mobile; flash de sucesso após salvar algo.

- [ ] **Step 5: Commit**

```bash
git add resources/js/components/ui/dropdown.tsx resources/js/components/app/backdrop.tsx resources/js/components/ui/alert.tsx
git commit -m "feat: anima entrada de dropdown, backdrop e alertas"
```

---

### Task 7: Micro-feedback do Button e transições dos inputs

**Files:**
- Modify: `resources/js/components/ui/button.tsx`
- Modify: `resources/js/components/form/input.tsx`
- Modify: `resources/js/components/form/select.tsx`
- Modify: `resources/js/components/form/switch.tsx`
- Modify: `resources/js/components/form/checkbox.tsx`

- [ ] **Step 1: Adicionar feedback de clique no Button**

Na string de classes do `<button>` (linha ~78), trocar `transition` por `transition active:scale-[0.98]`:

```tsx
            className={`inline-flex items-center justify-center gap-2 rounded-lg transition active:scale-[0.98] disabled:cursor-not-allowed ${
```

- [ ] **Step 2: Padronizar transição de foco/estado nos campos de formulário**

Ler cada arquivo antes de editar. Em `input.tsx` e `select.tsx`: garantir que a classe `transition` (cores/borda/sombra) exista no elemento de campo — se ausente, adicionar `transition` à lista de classes do `<input>`/`<select>`. Em `switch.tsx` e `checkbox.tsx`: confirmar que o deslizamento do knob e a troca de cor usam `transition` (o switch já tem 2 ocorrências — apenas conferir); adicionar `transition` onde faltar no contêiner de cor e no knob.

- [ ] **Step 3: Verificar**

Run: `npm run typecheck`
Expected: sem erros. Visual: focar um input (borda/anel suaves), alternar um switch (knob desliza), clicar e segurar um botão primário (leve compressão).

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ui/button.tsx resources/js/components/form/input.tsx resources/js/components/form/select.tsx resources/js/components/form/switch.tsx resources/js/components/form/checkbox.tsx
git commit -m "feat: padroniza micro-interacoes de botoes e campos de formulario"
```

---

### Task 8: Entrada de KPIs e EmptyState

**Files:**
- Modify: `resources/js/components/ui/kpi-card.tsx`
- Modify: `resources/js/components/ui/empty-state.tsx`

Ler os dois arquivos antes de editar (estrutura exata pode variar).

Nota de escopo: `Card` e `PageHeader` NÃO recebem animação própria — o fade-in de página do `AppShell` (Task 4) já cobre a entrada deles; animar individualmente duplicaria o efeito. KpiCard é exceção por ser grade de destaque com stagger.

- [ ] **Step 1: KpiCard com entrada e stagger opcional**

Em `kpi-card.tsx`, adicionar prop opcional e aplicar no contêiner raiz:

```tsx
interface KpiCardProps {
    // ...props existentes...
    /** Índice para entrada escalonada em grades de KPIs (delay de 60ms por item). */
    staggerIndex?: number;
}
```

No contêiner raiz do card:

```tsx
<div
    className="animate-fade-in-up ..." // manter classes existentes
    style={staggerIndex !== undefined ? { animationDelay: `${staggerIndex * 60}ms` } : undefined}
>
```

Nos dashboards que renderizam grades de KPIs (`resources/js/pages/gestao/dashboard.tsx`, `resources/js/pages/portal/dashboard.tsx`), passar `staggerIndex={index}` no map.

- [ ] **Step 2: EmptyState com fade**

Em `empty-state.tsx`, adicionar `animate-fade-in` ao contêiner raiz.

- [ ] **Step 3: Verificar**

Run: `npm run typecheck`
Expected: sem erros. Visual: dashboard da gestão — KPIs entram em sequência sutil.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ui/kpi-card.tsx resources/js/components/ui/empty-state.tsx resources/js/pages/gestao/dashboard.tsx resources/js/pages/portal/dashboard.tsx
git commit -m "feat: anima entrada de kpis e empty states"
```

---

### Task 9: DataTable adaptativo — tipos e derivação do layout mobile (TDD)

**Files:**
- Modify: `resources/js/components/ui/data-table/types.ts`
- Create: `resources/js/components/ui/data-table/mobile-layout.ts`
- Test: `tests/js/components/data-table/mobile-layout.test.ts`

- [ ] **Step 1: Estender `ColumnDef` em `types.ts`**

```ts
export type MobileRole = 'title' | 'detail' | 'actions' | 'hidden';

export interface MobileColumnConfig {
    /** Papel da coluna no card mobile. Default: primeira coluna = title, demais = detail. */
    role?: MobileRole;
    /** Rótulo no card; default: `header` quando for string. */
    label?: string;
}

export interface ColumnDef<T> {
    /** Identificador estável — para colunas ordenáveis, é o valor enviado em `sort`. */
    id: string;
    header: ReactNode;
    sortable?: boolean;
    align?: 'start' | 'center' | 'end';
    headerClassName?: string;
    cellClassName?: string;
    /** Comportamento no modo card (telas < md). */
    mobile?: MobileColumnConfig;
    cell: (row: T) => ReactNode;
}
```

- [ ] **Step 2: Escrever teste que falha**

`tests/js/components/data-table/mobile-layout.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import { deriveMobileLayout } from '@/components/ui/data-table/mobile-layout';
import type { ColumnDef } from '@/components/ui/data-table/types';

interface Row {
    name: string;
}

function column(overrides: Partial<ColumnDef<Row>> & { id: string }): ColumnDef<Row> {
    return { header: overrides.id, cell: (row) => row.name, ...overrides };
}

describe('deriveMobileLayout', () => {
    it('sem anotações: primeira coluna vira title e demais viram detail', () => {
        const layout = deriveMobileLayout([column({ id: 'a' }), column({ id: 'b' }), column({ id: 'c' })]);

        expect(layout.title?.id).toBe('a');
        expect(layout.details.map((d) => d.id)).toEqual(['b', 'c']);
        expect(layout.actions).toHaveLength(0);
    });

    it('role title explícito tem prioridade sobre a primeira coluna', () => {
        const layout = deriveMobileLayout([
            column({ id: 'a' }),
            column({ id: 'b', mobile: { role: 'title' } }),
        ]);

        expect(layout.title?.id).toBe('b');
        expect(layout.details.map((d) => d.id)).toEqual(['a']);
    });

    it('separa actions e omite hidden', () => {
        const layout = deriveMobileLayout([
            column({ id: 'a' }),
            column({ id: 'acoes', mobile: { role: 'actions' } }),
            column({ id: 'interna', mobile: { role: 'hidden' } }),
        ]);

        expect(layout.details).toHaveLength(0);
        expect(layout.actions.map((c) => c.id)).toEqual(['acoes']);
    });

    it('labelOf usa mobile.label, depois header string, depois vazio', () => {
        const layout = deriveMobileLayout([
            column({ id: 'a' }),
            column({ id: 'b', header: 'Coluna B' }),
            column({ id: 'c', header: 'Coluna C', mobile: { label: 'Rótulo C' } }),
        ]);

        expect(layout.labelOf(layout.details[0])).toBe('Coluna B');
        expect(layout.labelOf(layout.details[1])).toBe('Rótulo C');
    });
});
```

- [ ] **Step 3: Rodar e verificar que falha**

Run: `npm test -- tests/js/components/data-table/mobile-layout.test.ts`
Expected: FAIL — módulo `mobile-layout` não existe.

- [ ] **Step 4: Implementar `resources/js/components/ui/data-table/mobile-layout.ts`**

```ts
import type { ColumnDef } from './types';

export interface MobileLayout<T> {
    title: ColumnDef<T> | null;
    details: ColumnDef<T>[];
    actions: ColumnDef<T>[];
    labelOf: (column: ColumnDef<T>) => string;
}

/**
 * Deriva o layout do modo card (telas < md) a partir das colunas da
 * tabela: papel explícito em `mobile.role` ou, por padrão, primeira
 * coluna como título e demais como pares rótulo/valor.
 */
export function deriveMobileLayout<T>(columns: ColumnDef<T>[]): MobileLayout<T> {
    const explicitTitle = columns.find((column) => column.mobile?.role === 'title') ?? null;
    const fallbackTitle = columns.find((column) => !column.mobile?.role) ?? null;
    const title = explicitTitle ?? fallbackTitle;

    const details: ColumnDef<T>[] = [];
    const actions: ColumnDef<T>[] = [];

    for (const column of columns) {
        if (column === title || column.mobile?.role === 'hidden') {
            continue;
        }

        if (column.mobile?.role === 'actions') {
            actions.push(column);
            continue;
        }

        details.push(column);
    }

    return {
        title,
        details,
        actions,
        labelOf: (column) =>
            column.mobile?.label ?? (typeof column.header === 'string' ? column.header : ''),
    };
}
```

- [ ] **Step 5: Rodar e verificar que passa**

Run: `npm test -- tests/js/components/data-table/mobile-layout.test.ts`
Expected: 4 passed.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ui/data-table/types.ts resources/js/components/ui/data-table/mobile-layout.ts tests/js/components/data-table/mobile-layout.test.ts
git commit -m "feat: deriva layout mobile das colunas do data table"
```

---

### Task 10: DataTable adaptativo — renderização dos cards (TDD)

**Files:**
- Modify: `resources/js/components/ui/data-table/data-table.tsx`
- Test: `tests/js/components/data-table/data-table-mobile.test.tsx`

- [ ] **Step 0: Instalar dependência de teste**

```bash
npm install -D @testing-library/user-event
```

- [ ] **Step 1: Escrever teste que falha**

`tests/js/components/data-table/data-table-mobile.test.tsx`:

```tsx
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';

interface Row {
    id: number;
    name: string;
    email: string;
}

const rows: Row[] = [
    { id: 1, name: 'Maria Silva', email: 'maria@example.com' },
    { id: 2, name: 'João Souza', email: 'joao@example.com' },
];

const columns: ColumnDef<Row>[] = [
    { id: 'name', header: 'Nome', sortable: true, cell: (row) => row.name },
    { id: 'email', header: 'E-mail', cell: (row) => row.email },
    {
        id: 'actions',
        header: 'Ações',
        mobile: { role: 'actions' },
        cell: (row) => <button type="button">Editar {row.name}</button>,
    },
];

describe('DataTable — modo card mobile', () => {
    it('renderiza um card por linha com título, detalhes e ações', () => {
        render(<DataTable columns={columns} rows={rows} rowKey={(row) => row.id} />);

        const list = screen.getByRole('list');
        const cards = within(list).getAllByRole('listitem');

        expect(cards).toHaveLength(2);
        expect(within(cards[0]).getByText('Maria Silva')).toBeInTheDocument();
        expect(within(cards[0]).getByText('E-mail')).toBeInTheDocument();
        expect(within(cards[0]).getByText('maria@example.com')).toBeInTheDocument();
        expect(within(cards[0]).getByRole('button', { name: 'Editar Maria Silva' })).toBeInTheDocument();
    });

    it('exibe select de ordenação que dispara onSortChange', async () => {
        const onSortChange = vi.fn();
        const user = userEvent.setup();

        render(
            <DataTable
                columns={columns}
                rows={rows}
                rowKey={(row) => row.id}
                sort={{ column: 'name', direction: 'asc' }}
                onSortChange={onSortChange}
            />,
        );

        await user.selectOptions(screen.getByLabelText('Ordenar por'), 'name:desc');

        expect(onSortChange).toHaveBeenCalledWith({ column: 'name', direction: 'desc' });
    });

    it('sem colunas ordenáveis não exibe o select de ordenação', () => {
        const plain = columns.map((column) => ({ ...column, sortable: false }));
        render(<DataTable columns={plain} rows={rows} rowKey={(row) => row.id} />);

        expect(screen.queryByLabelText('Ordenar por')).not.toBeInTheDocument();
    });

    it('exibe skeletons de card durante o loading', () => {
        const { container } = render(
            <DataTable columns={columns} rows={[]} rowKey={(row: Row) => row.id} loading skeletonRows={3} />,
        );

        expect(container.querySelectorAll('[data-slot="card-skeleton"]')).toHaveLength(3);
    });
});
```

- [ ] **Step 2: Rodar e verificar que falha**

Run: `npm test -- tests/js/components/data-table/data-table-mobile.test.tsx`
Expected: FAIL — não há `role="list"` (modo card ainda não existe).

- [ ] **Step 3: Implementar o modo card no `data-table.tsx`**

Estrutura final do retorno (a tabela atual fica intacta dentro do bloco desktop):

```tsx
import type { ReactNode } from 'react';
import { ChevronDownIcon, SortIcon } from '@/components/icons';
import { Skeleton } from '@/components/ui/skeleton';
import { deriveMobileLayout } from './mobile-layout';
import type { ColumnDef, SortState } from './types';
```

Dentro do componente, antes do `return`:

```tsx
    const mobileLayout = deriveMobileLayout(columns);
    const sortableColumns = columns.filter((column) => column.sortable);
    const showMobileSort = sortableColumns.length > 0 && Boolean(onSortChange);
```

Retorno:

```tsx
    return (
        <div className="overflow-hidden rounded-xl border border-gray-200 dark:border-white/[0.05]">
            {/* Modo card (< md). display:none no breakpoint ativo mantém a
                árvore de acessibilidade sem conteúdo duplicado. */}
            <div className="md:hidden">
                {showMobileSort && (
                    <div className="border-b border-gray-100 px-4 py-3 dark:border-white/[0.05]">
                        <label className="flex items-center gap-2 text-theme-xs text-gray-500 dark:text-gray-400">
                            Ordenar por
                            <select
                                value={sort ? `${sort.column}:${sort.direction}` : ''}
                                onChange={(event) => {
                                    const [column, direction] = event.target.value.split(':');
                                    onSortChange?.({ column, direction: direction as SortState['direction'] });
                                }}
                                className="h-9 flex-1 appearance-none rounded-lg border border-gray-300 bg-transparent px-3 text-theme-sm text-gray-800 focus:border-brand-300 focus:ring-3 focus:ring-brand-500/20 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90"
                            >
                                {sortableColumns.flatMap((column) => [
                                    <option key={`${column.id}:asc`} value={`${column.id}:asc`}>
                                        {mobileLayout.labelOf(column)} (crescente)
                                    </option>,
                                    <option key={`${column.id}:desc`} value={`${column.id}:desc`}>
                                        {mobileLayout.labelOf(column)} (decrescente)
                                    </option>,
                                ])}
                            </select>
                        </label>
                    </div>
                )}
                {loading ? (
                    <div className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {Array.from({ length: skeletonRows }, (_, index) => (
                            <div key={index} data-slot="card-skeleton" className="flex flex-col gap-3 p-4">
                                <Skeleton className="h-5 w-2/3" />
                                <Skeleton className="h-4 w-full" />
                                <Skeleton className="h-4 w-1/2" />
                            </div>
                        ))}
                    </div>
                ) : rows.length > 0 ? (
                    <ul className="divide-y divide-gray-100 dark:divide-white/[0.05]">
                        {rows.map((row, index) => (
                            <li
                                key={rowKey(row)}
                                className="animate-fade-in-up flex flex-col gap-3 p-4"
                                style={{ animationDelay: `${Math.min(index, 10) * 50}ms` }}
                            >
                                {mobileLayout.title && (
                                    <div className="text-theme-sm font-medium text-gray-800 dark:text-white/90">
                                        {mobileLayout.title.cell(row)}
                                    </div>
                                )}
                                {mobileLayout.details.length > 0 && (
                                    <dl className="flex flex-col gap-2">
                                        {mobileLayout.details.map((column) => (
                                            <div key={column.id} className="flex items-start justify-between gap-3">
                                                <dt className="shrink-0 text-theme-xs font-medium text-gray-500 dark:text-gray-400">
                                                    {mobileLayout.labelOf(column)}
                                                </dt>
                                                <dd className="text-end text-theme-sm text-gray-700 dark:text-gray-300">
                                                    {column.cell(row)}
                                                </dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                                {mobileLayout.actions.length > 0 && (
                                    <div className="flex justify-end gap-2 border-t border-gray-100 pt-3 dark:border-white/[0.05]">
                                        {mobileLayout.actions.map((column) => (
                                            <div key={column.id}>{column.cell(row)}</div>
                                        ))}
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                ) : (
                    emptyState
                )}
            </div>

            {/* Tabela (>= md) — markup atual inalterado, embrulhado aqui. */}
            <div className="hidden md:block">
                <div className="max-w-full overflow-x-auto">
                    <table className="min-w-full">
                        {/* thead exatamente como hoje */}
                        {/* tbody como hoje, com duas mudanças: */}
                    </table>
                </div>
            </div>
        </div>
    );
```

No `<tbody>` da tabela desktop, duas mudanças pontuais para o fade na troca skeleton→dados (spec §3.3):

```tsx
                    <tbody
                        key={loading ? 'skeleton' : 'data'}
                        className={`divide-y divide-gray-100 dark:divide-white/[0.05] ${loading ? '' : 'animate-fade-in'}`}
                    >
```

A `key` remonta o corpo quando o loading termina, disparando o `animate-fade-in` uma única vez por troca de dados.

Nota jsdom: o teste enxerga os dois modos (CSS não é aplicado); os seletores dos testes (`getByRole('list')`, `data-slot`) miram apenas o bloco mobile, então não há ambiguidade.

- [ ] **Step 4: Rodar e verificar que passa**

Run: `npm test -- tests/js/components/data-table/data-table-mobile.test.tsx`
Expected: 4 passed.

- [ ] **Step 5: Rodar suíte completa + typecheck**

Run: `npm test && npm run typecheck`
Expected: tudo verde.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ui/data-table/data-table.tsx tests/js/components/data-table/data-table-mobile.test.tsx package.json package-lock.json
git commit -m "feat: adiciona modo card mobile ao data table"
```

---

### Task 11: Anotar colunas de ações nas páginas com DataTable

**Files:**
- Modify: `resources/js/pages/portal/empresas/index.tsx:130-133`
- Modify: `resources/js/pages/gestao/usuarios/index.tsx:234-237`
- Modify: `resources/js/pages/gestao/perfis/index.tsx:327-330`
- Modify: `resources/js/pages/gestao/cnaes/index.tsx:324-327`

As demais páginas com DataTable (`gestao/parametros/historico.tsx`, `gestao/acessos.tsx`, `gestao/emails/index.tsx`) não têm coluna de ações — os defaults (primeira coluna = título, demais = detalhe) já resolvem; nenhuma mudança nelas.

- [ ] **Step 1: Anotar a coluna `actions` nas 4 páginas**

Em cada uma, na definição da coluna `actions`, adicionar `mobile: { role: 'actions' }`. Exemplo em `portal/empresas/index.tsx`:

```tsx
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            cellClassName: 'whitespace-nowrap',
            mobile: { role: 'actions' },
            cell: (company) => (
```

Aplicar o mesmo acréscimo nas outras 3 páginas (mesma estrutura de coluna).

- [ ] **Step 2: Verificar**

Run: `npm run typecheck`
Expected: sem erros.

Visual (responsivo, viewport < 768px): `/portal/empresas` e `/gestao/usuarios` mostram cards com título, pares rótulo/valor e ações no rodapé; em desktop a tabela permanece idêntica.

- [ ] **Step 3: Commit**

```bash
git add resources/js/pages/portal/empresas/index.tsx resources/js/pages/gestao/usuarios/index.tsx resources/js/pages/gestao/perfis/index.tsx resources/js/pages/gestao/cnaes/index.tsx
git commit -m "feat: habilita modo card mobile nas listagens existentes"
```

---

### Task 12: Migrar tabelas de procurações para DataTable

**Files:**
- Modify: `resources/js/pages/portal/procuracoes/index.tsx`

- [ ] **Step 1: Substituir `GrantedTable` por DataTable client-side**

Remover o markup `<Table>` manual (linhas ~189-253) e usar colunas tipadas (sem `sortable` — listagem pequena, sem servidor):

```tsx
function GrantedTable({
    granted,
    canGrant,
    onRevoke,
}: {
    granted: ProcurationItem[];
    canGrant: boolean;
    onRevoke: (item: ProcurationItem) => void;
}) {
    if (granted.length === 0) {
        return (
            <EmptyState
                icon={<FileIcon className="size-7" aria-hidden="true" />}
                title="Nenhuma procuração outorgada"
                description="Você ainda não vinculou um procurador para atuar em seu nome."
                action={
                    canGrant ? (
                        <Button size="sm" variant="outline" onClick={focusGrantForm}>
                            Vincular procurador
                        </Button>
                    ) : undefined
                }
            />
        );
    }

    const columns: ColumnDef<ProcurationItem>[] = [
        {
            id: 'name',
            header: 'Procurador',
            cell: (item) => (
                <span className="font-medium text-gray-800 dark:text-white/90">{item.name}</span>
            ),
        },
        { id: 'email', header: 'E-mail', cell: (item) => item.email },
        {
            id: 'starts_at',
            header: 'Início',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatDate(item.starts_at),
        },
        {
            id: 'expires_at',
            header: 'Validade',
            cellClassName: 'whitespace-nowrap',
            cell: (item) => formatDate(item.expires_at),
        },
        { id: 'situation', header: 'Situação', cell: (item) => <SituationBadge item={item} /> },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            mobile: { role: 'actions' },
            cell: (item) =>
                item.is_active ? (
                    <button type="button" onClick={() => onRevoke(item)} className={errorActionStyles}>
                        Revogar
                    </button>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
    ];

    return <DataTable columns={columns} rows={granted} rowKey={(item) => item.id} />;
}
```

- [ ] **Step 2: Substituir `ReceivedTable` no mesmo padrão**

```tsx
function ReceivedTable({ received }: { received: ProcurationItem[] }) {
    if (received.length === 0) {
        return (
            <EmptyState
                icon={<FileIcon className="size-7" aria-hidden="true" />}
                title="Nenhuma procuração recebida"
                description="Quando um interessado vincular você como procurador, o registro aparecerá aqui."
            />
        );
    }

    const columns: ColumnDef<ProcurationItem>[] = [
        {
            id: 'name',
            header: 'Outorgante',
            cell: (item) => (
                <span className="font-medium text-gray-800 dark:text-white/90">{item.name}</span>
            ),
        },
        { id: 'email', header: 'E-mail', cell: (item) => item.email },
        { id: 'situation', header: 'Situação', cell: (item) => <SituationBadge item={item} /> },
        {
            id: 'actions',
            header: 'Ações',
            align: 'end',
            mobile: { role: 'actions' },
            cell: (item) =>
                item.is_active ? (
                    <Link
                        href="/portal/representacao"
                        method="post"
                        data={{ procuration_id: item.id }}
                        as="button"
                        className={brandActionStyles}
                    >
                        Atuar em nome de
                    </Link>
                ) : (
                    <span className="text-gray-400 dark:text-gray-500">—</span>
                ),
        },
    ];

    return <DataTable columns={columns} rows={received} rowKey={(item) => item.id} />;
}
```

- [ ] **Step 3: Ajustar imports e scroll acessível**

- Remover import de `Table, TableBody, TableCell, TableHeader, TableRow` e as constantes `headerCellStyles`/`bodyCellStyles`.
- Adicionar:

```tsx
import DataTable from '@/components/ui/data-table/data-table';
import type { ColumnDef } from '@/components/ui/data-table/types';
import { prefersReducedMotion } from '@/lib/motion';
```

- Em `focusGrantForm`, respeitar reduced-motion:

```tsx
function focusGrantForm() {
    const input = document.getElementById('attorney_email');

    if (input instanceof HTMLInputElement) {
        input.scrollIntoView({ behavior: prefersReducedMotion() ? 'auto' : 'smooth', block: 'center' });
        input.focus({ preventScroll: true });
    }
}
```

- [ ] **Step 4: Verificar**

Run: `npm test && npm run typecheck`
Expected: tudo verde.

Backend (sanidade — a página continua recebendo as mesmas props):

Run: `php artisan test --compact --filter=Procuration`
Expected: testes de procuração passando (se o filtro não achar nada, rodar `php artisan test --compact tests/Feature` é desnecessário — conferir nome exato com `ls tests/Feature`).

Visual: `/portal/procuracoes` em mobile mostra cards; em desktop, tabelas equivalentes às anteriores.

- [ ] **Step 5: Commit**

```bash
git add resources/js/pages/portal/procuracoes/index.tsx
git commit -m "refactor: migra tabelas de procuracoes para data table adaptativo"
```

---

### Task 13: Rule de motion como padrão permanente

**Files:**
- Create: `.cursor/rules/motion.mdc`

- [ ] **Step 1: Criar a rule**

```markdown
---
alwaysApply: true
---
# Motion — Padrão de Fluidez

Tokens e utilities centrais em `resources/css/app.css` (@theme). Nada de durações/easings improvisados.

- Entradas: `animate-fade-in`, `animate-fade-in-up`, `animate-fade-in-down`, `animate-scale-in`; saídas: `animate-fade-out`, `animate-scale-out`
- Easings: `--ease-fluid` (entradas), `--ease-snappy` (micro-interações); durações 150/200/250ms
- Stagger: `animationDelay` incremental (50-60ms), limitado aos ~10 primeiros itens
- Elementos condicionais com saída animada: hook `useExitTransition` (`resources/js/hooks/use-exit-transition.ts`)
- Scroll programático: checar `prefersReducedMotion()` (`resources/js/lib/motion.ts`)
- Toda animação nova DEVE funcionar sob `prefers-reduced-motion: reduce` (bloco global já cobre CSS; JS é responsabilidade de quem escreve)
- Listagens novas usam `DataTable` (adaptativo: cards < md, tabela >= md); coluna de ações com `mobile: { role: 'actions' }`
```

- [ ] **Step 2: Commit**

```bash
git add .cursor/rules/motion.mdc
git commit -m "docs: registra padrao de motion como rule do projeto"
```

---

### Task 14: Verificação final de ponta a ponta

- [ ] **Step 1: Suíte completa**

Run: `npm test && npm run typecheck && npm run build`
Expected: tudo verde, build sem erros.

- [ ] **Step 2: Sanidade backend**

Run: `php artisan test --compact`
Expected: suíte passa (nenhuma mudança de backend nesta fase; falhas aqui seriam pré-existentes — reportar sem mascarar).

- [ ] **Step 3: Evidência visual**

Com o dev server ativo (`composer run dev`), capturar com a skill do Playwright:

1. `/portal/empresas` em viewport 390x844 (cards) e 1440x900 (tabela)
2. `/portal/procuracoes` em 390x844
3. Dashboard da gestão em 1440x900 (stagger dos KPIs)
4. Um ConfirmDialog aberto (animação de entrada)

Salvar em `output/playwright/` com prefixo `fluidez-`.

- [ ] **Step 4: Atualizar status da spec**

Em `docs/superpowers/specs/2026-06-12-fluidez-ux-design.md`, trocar a linha de status para `Status: implementado` e registrar a decisão do §7 como aprovada (vitest adicionado).

```bash
git add docs/superpowers/specs/2026-06-12-fluidez-ux-design.md
git commit -m "docs: marca spec de fluidez como implementada"
```
