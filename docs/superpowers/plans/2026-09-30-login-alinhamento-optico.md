# Login de gestão — alinhamento óptico: plano de implementação

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Substituir o mapa de zoneamento estático do palco do login de gestão por uma cena animada de três anéis ópticos que se alinham conforme o estado da sessão, terminando numa transição sem piscada entre `/gestao/login` e o console.

**Architecture:** O login ganha quatro módulos — um puro com a máquina de estados e a matemática da cena, um com o markup e o loop `requestAnimationFrame`, um com a orquestração pós-envio, e o `gestao-login.tsx` reduzido a palco mais formulário. O envio deixa de ser um POST Inertia (que trocaria a página na hora) e passa a ser uma requisição solta via `useHttp`, contra um contrato novo no `LoginController` que devolve o destino em JSON; a navegação acontece ao fim da animação.

**Tech Stack:** Laravel 13, PHPUnit 12, Inertia v3 (`@inertiajs/react` 3.3.1, `useHttp`), React 19, Tailwind 4, Vitest 4.

**Spec:** `docs/superpowers/specs/2026-09-30-login-alinhamento-optico-design.md`
**Handoff de design:** `~/Downloads/design_handoff_login_alinhamento` (README, `prototipo/Login Gestao v3.dc.html`, `screenshots/`)

## Global Constraints

- Idioma: UI, mensagens, comentários, nomes de teste (`test_...` / `it(...)`) e commits em pt-BR. Nomes de variáveis, funções, classes e tipos em inglês.
- Commits no formato conventional commits em português, minúsculas, sem ponto final.
- TDD estrito: nenhum código de produção sem um teste falhando antes. Rodar o teste e confirmar que falha pelo motivo certo.
- `vendor/bin/pint --dirty --format agent` após alterar qualquer arquivo PHP.
- Sem features de fachada: nada de resultado simulado, nada de etapa fingida.
- Não adicionar dependência nova. `motion` continua sendo o padrão do projeto; o loop rAF do login é a exceção justificada na spec.
- O rótulo da LOUOS é **Lei nº 9.148/2016**. O protótipo escreve 9.184/2016, que é erro de digitação — não copiar.
- Textos do palco são os atuais do repositório ("O motor decide. / O analista confirma.", eyebrow "Decisão de viabilidade locacional", rodapé Expresso / Análise / LOUOS). O protótipo tem textos antigos — não copiar.
- Centro do `LogoMark` da sidebar do console na viewport: `(38px, 50px)`. Tamanho: 36px. Cor: `#5992ed` (`text-brand-400`).
- Cores fixas da cena: acento `oklch(64% 0.155 250)`, tile da marca `oklch(47% 0.135 252)`, erro `oklch(64% 0.19 25)`, véu do console `#0c111d`, conteúdo do console `#f9fafb`.
- Breakpoint do palco: 1101px. Abaixo disso, nem o palco nem a cena são renderizados.

---

## Estrutura de arquivos

| Arquivo | Ação | Responsabilidade |
|---|---|---|
| `app/Http/Controllers/Gestao/LoginController.php` | Modificar (linhas 47, 121-127) | Responder o destino em JSON quando houver o header assíncrono |
| `tests/Feature/Auth/GestaoLoginAsyncTest.php` | Criar | Contrato assíncrono do login |
| `resources/js/pages/auth/gestao-login-scene-state.ts` | Criar | Puro: classificação do identificador, estado lógico, alvos, deriva, tempos, textos |
| `resources/js/pages/auth/gestao-login-scene-state.test.ts` | Criar | Testes do módulo puro |
| `resources/js/pages/auth/gestao-login-theme.ts` | Modificar (linhas 6-26) | Shell em flex, palco sem a foto no background |
| `resources/js/pages/auth/gestao-login-theme.test.ts` | Modificar | Asserções do shell em flex e do palco sem foto |
| `resources/js/pages/auth/gestao-login-scene.tsx` | Criar | Markup da cena e o loop `requestAnimationFrame` |
| `resources/js/pages/auth/gestao-login-docking.ts` | Criar | Orquestração pós-envio e a marca de entrada no console |
| `resources/js/pages/auth/gestao-login-docking.test.ts` | Criar | Testes da orquestração com temporizadores falsos |
| `resources/js/pages/auth/gestao-login.tsx` | Modificar | Palco, formulário e a ligação com a cena |
| `resources/js/components/app/console-entrance.ts` | Criar | Marca de entrada no console em `sessionStorage` |
| `resources/js/components/app/console-entrance.test.ts` | Criar | Testes da marca |
| `resources/js/components/app/app-shell.tsx` | Modificar (linhas 34-35) | Marcar o wrapper quando a entrada vier do login |
| `resources/css/app.css` | Modificar | Keyframes e cascata da entrada no console |

**Decisão herdada da exploração:** a cascata dos blocos de conteúdo do console **já existe** — `PageTransition` monta um `RevealScope` e o `dashboard.tsx` usa `Surface`/`RevealLink`, que entram em fila escalonada. A entrada do console só precisa animar a sidebar e o header; duplicar a cascata no conteúdo causaria animação dupla.

---

### Task 1: Contrato assíncrono do login

**Files:**
- Modify: `app/Http/Controllers/Gestao/LoginController.php:47` e `:121-127`
- Test: `tests/Feature/Auth/GestaoLoginAsyncTest.php`

**Interfaces:**
- Consumes: nada de tarefas anteriores.
- Produces: `POST /gestao/login` com o header `X-Viabiliza-Login-Async` responde `200` com o corpo `{"destino": "<url absoluta>"}`. Sem o header, continua respondendo `302`. Consumido pela Task 5.

- [ ] **Step 1: Escrever o teste falhando**

Criar `tests/Feature/Auth/GestaoLoginAsyncTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Contrato assíncrono do login do console: com o header
 * X-Viabiliza-Login-Async o destino volta em JSON, para o front rodar a
 * animação de entrada antes de navegar. Sem o header, o POST continua
 * redirecionando — o formulário sem JavaScript segue funcionando.
 */
class GestaoLoginAsyncTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_com_o_header_assincrono_o_destino_volta_em_json(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertOk()
            ->assertExactJson(['destino' => route('gestao.dashboard')]);

        $this->assertAuthenticatedAs($administrador, 'gestao');
    }

    public function test_sem_o_header_o_login_continua_redirecionando(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->post('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ])->assertRedirect('/gestao');

        $this->assertAuthenticatedAs($administrador, 'gestao');
    }

    public function test_credencial_invalida_no_modo_assincrono_devolve_422(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'senha-errada',
        ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest('gestao');
    }

    public function test_modo_assincrono_respeita_a_url_pretendida(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->withSession(['url.intended' => '/gestao/parametros'])
            ->postJson('/gestao/login', [
                'identifier' => $administrador->email,
                'password' => 'password',
            ], ['X-Viabiliza-Login-Async' => '1'])
            ->assertOk()
            ->assertExactJson(['destino' => '/gestao/parametros']);
    }

    public function test_modo_assincrono_registra_a_tentativa_na_auditoria(): void
    {
        $administrador = User::factory()->administrador()->create();

        $this->postJson('/gestao/login', [
            'identifier' => $administrador->email,
            'password' => 'password',
        ], ['X-Viabiliza-Login-Async' => '1'])->assertOk();

        $this->assertDatabaseHas('access_logs', [
            'user_id' => $administrador->id,
            'event' => 'login',
            'channel' => 'gestao',
        ]);
    }
}
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAsyncTest.php`
Expected: FAIL. `test_com_o_header_assincrono_o_destino_volta_em_json` e `test_modo_assincrono_respeita_a_url_pretendida` falham com status 302 em vez de 200. Os outros três já passam — eles guardam o comportamento que não pode regredir.

- [ ] **Step 3: Implementar**

Em `app/Http/Controllers/Gestao/LoginController.php`, adicionar o import depois de `use Illuminate\Auth\Events\Lockout;`:

```php
use Illuminate\Http\JsonResponse;
```

Trocar a assinatura de `store` (linha 47):

```php
    public function store(LoginRequest $request): RedirectResponse|JsonResponse
```

Trocar o bloco final de `store` (linhas 121-126):

```php
        $destino = $this->intendedRedirect->resolveTarget(
            $request,
            route('gestao.dashboard'),
        );

        // O console anima a entrada antes de navegar (cena de alinhamento do
        // login). Um redirect aqui faria o Inertia trocar a página na hora e
        // cortar a animação — com o header, o destino volta em JSON e a
        // navegação acontece no fim da sequência.
        if ($request->hasHeader('X-Viabiliza-Login-Async')) {
            return response()->json(['destino' => $destino]);
        }

        return redirect($destino);
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `php artisan test --compact tests/Feature/Auth/GestaoLoginAsyncTest.php`
Expected: PASS, 5 testes.

- [ ] **Step 5: Confirmar que o login existente não regrediu**

Run: `php artisan test --compact tests/Feature/Auth/`
Expected: PASS em toda a pasta.

- [ ] **Step 6: Formatar e commitar**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Gestao/LoginController.php tests/Feature/Auth/GestaoLoginAsyncTest.php
git commit -m "feat: login do console devolve o destino em json no modo assíncrono"
```

---

### Task 2: Máquina de estados e matemática da cena

**Files:**
- Create: `resources/js/pages/auth/gestao-login-scene-state.ts`
- Test: `resources/js/pages/auth/gestao-login-scene-state.test.ts`

**Interfaces:**
- Consumes: nada.
- Produces (usado pelas Tasks 4, 5 e 6):
  - `type FaseLogin = 'idle' | 'validating' | 'denied' | 'authorized' | 'preparing' | 'docking'`
  - `type EstadoLogico = FaseLogin | 'session' | 'secure' | 'ready'`
  - `type TipoConta = { rotulo: string; selo: string }`
  - `classificarIdentificador(valor: string): TipoConta | null`
  - `estadoLogico(entrada: { fase: FaseLogin; identificador: string; temSenha: boolean; comFoco: boolean; tipoConta: TipoConta | null }): EstadoLogico`
  - `alvosPorEstado(estado: EstadoLogico, base: { x: number; y: number }, rotacaoBase: number, alvoYLinha: number): AlvosCena`
  - `type AlvosCena` — objeto plano de números, com as chaves `drift`, `rs`, `sec_c`, `sec_o`, `sec_rot`, `sec_red`, `disc_s`, `disc_x`, `disc_y`, `disc_k`, `disc_o`, `ph_b`, `ph_s`, `ph_br`, `ax_o`, `ax_len`, `ax_y` e, para cada anel `k` em `a`/`b`/`c`, `${k}_x`, `${k}_y`, `${k}_s`, `${k}_b`, `${k}_o`
  - `deriva(indice: number, tempoMs: number, fator: number): { dx: number; dy: number }`
  - `TAXAS: Record<string, number>` e `TAXA_PADRAO = 3.2`
  - `TEMPOS = { pisoValidacao: 1450, preparing: 900, docking: 2000, navegacao: 2900, denied: 1100, debounceIdentificador: 520 }`
  - `TEXTOS_ESTADO: Record<EstadoLogico, { numero: string; texto: string }>`
  - `corDoEstado(estado: EstadoLogico): string`
  - `ESTADOS_FUNDIDOS: EstadoLogico[]` e `ESTADOS_SHELL: FaseLogin[]`

- [ ] **Step 1: Escrever o teste falhando**

Criar `resources/js/pages/auth/gestao-login-scene-state.test.ts`:

```ts
import { describe, expect, it } from 'vitest';
import {
    ESTADOS_SHELL,
    TEMPOS,
    alvosPorEstado,
    classificarIdentificador,
    corDoEstado,
    deriva,
    estadoLogico,
} from './gestao-login-scene-state';
import type { TipoConta } from './gestao-login-scene-state';

const contaLocal: TipoConta = { rotulo: 'Conta local', selo: 'Conta local' };

function entrada(parcial: Partial<Parameters<typeof estadoLogico>[0]> = {}) {
    return {
        fase: 'idle' as const,
        identificador: '',
        temSenha: false,
        comFoco: false,
        tipoConta: null,
        ...parcial,
    };
}

describe('Classificação do identificador', () => {
    it('reconhece o e-mail institucional pelo domínio da prefeitura', () => {
        expect(classificarIdentificador('maria.souza@salvador.ba.gov.br')).toEqual({
            rotulo: 'E-mail institucional',
            selo: 'E-mail institucional',
        });
    });

    it('reconhece qualquer outro e-mail válido como conta local', () => {
        expect(classificarIdentificador('maria@exemplo.com')).toEqual({
            rotulo: 'Conta local',
            selo: 'Conta local',
        });
    });

    it('reconhece usuário sem arroba como rede corporativa', () => {
        expect(classificarIdentificador('msouza')).toEqual({
            rotulo: 'Rede corporativa (AD)',
            selo: 'Rede corporativa · AD',
        });
    });

    it('não classifica campo vazio, usuário curto demais nem e-mail incompleto', () => {
        expect(classificarIdentificador('')).toBeNull();
        expect(classificarIdentificador('   ')).toBeNull();
        expect(classificarIdentificador('ab')).toBeNull();
        expect(classificarIdentificador('maria@')).toBeNull();
    });
});

describe('Estado lógico derivado', () => {
    it('em repouso, sem foco e sem nada digitado, é idle', () => {
        expect(estadoLogico(entrada())).toBe('idle');
    });

    it('foco em qualquer campo já abre a sessão', () => {
        expect(estadoLogico(entrada({ comFoco: true }))).toBe('session');
    });

    it('identificador digitado mantém a sessão mesmo sem foco', () => {
        expect(estadoLogico(entrada({ identificador: 'msouza' }))).toBe('session');
    });

    it('senha iniciada leva a secure mesmo sem identificador reconhecido', () => {
        expect(estadoLogico(entrada({ temSenha: true, identificador: 'ab' }))).toBe('secure');
    });

    it('identificador reconhecido mais senha leva a ready', () => {
        expect(
            estadoLogico(entrada({ identificador: 'msouza', temSenha: true, tipoConta: contaLocal })),
        ).toBe('ready');
    });

    it('a fase tem precedência sobre tudo que foi digitado', () => {
        expect(
            estadoLogico(
                entrada({ fase: 'validating', identificador: 'msouza', temSenha: true, tipoConta: contaLocal }),
            ),
        ).toBe('validating');
        expect(estadoLogico(entrada({ fase: 'denied' }))).toBe('denied');
        expect(estadoLogico(entrada({ fase: 'docking' }))).toBe('docking');
    });
});

describe('Alvos por estado', () => {
    const base = { x: 700, y: 400 };

    it('em idle os três anéis ficam espalhados, desfocados e com o arco apagado', () => {
        const alvos = alvosPorEstado('idle', base, 0, 500);

        expect(alvos.a_x).toBe(-130);
        expect(alvos.a_y).toBe(-72);
        expect(alvos.c_b).toBe(3.2);
        expect(alvos.sec_c).toBe(0);
        expect(alvos.sec_o).toBe(0);
        expect(alvos.ax_o).toBe(0);
        expect(alvos.drift).toBe(1);
    });

    it('em ready os anéis quase se tocam e o arco vai a 86', () => {
        const alvos = alvosPorEstado('ready', base, 0, 500);

        expect(alvos.a_x).toBe(-16);
        expect(alvos.sec_c).toBe(86);
        expect(alvos.drift).toBe(0.12);
        expect(alvos.ax_len).toBe(1);
    });

    it('em validating os três anéis vão ao centro e o arco fecha girando 360 graus', () => {
        const alvos = alvosPorEstado('validating', base, 720, 500);

        expect(alvos.a_x).toBe(0);
        expect(alvos.b_x).toBe(0);
        expect(alvos.c_x).toBe(0);
        expect(alvos.sec_c).toBe(100);
        expect(alvos.sec_rot).toBe(1080);
        expect(alvos.ax_len).toBe(0);
    });

    it('em denied o arco fica vermelho e os anéis se afastam', () => {
        const alvos = alvosPorEstado('denied', base, 0, 500);

        expect(alvos.sec_red).toBe(1);
        expect(alvos.sec_c).toBe(16);
        expect(alvos.a_x).toBe(-165);
        expect(alvos.drift).toBe(1.3);
    });

    it('em authorized o disco aparece e a foto perde o desfoque', () => {
        const alvos = alvosPorEstado('authorized', base, 0, 500);

        expect(alvos.disc_s).toBe(1);
        expect(alvos.ph_b).toBe(0);
        expect(alvos.sec_o).toBe(0.55);
    });

    it('em docking o disco viaja até o logo da sidebar e encolhe para 36px', () => {
        const alvos = alvosPorEstado('docking', base, 0, 500);

        expect(alvos.disc_x).toBe(38 - base.x);
        expect(alvos.disc_y).toBe(50 - base.y);
        expect(alvos.disc_k).toBe(0.3);
        expect(alvos.rs).toBe(3.4);
        expect(alvos.a_o).toBe(0);
        expect(alvos.drift).toBe(0);
    });
});

describe('Deriva dos anéis', () => {
    it('zera quando o fator do estado é zero', () => {
        expect(deriva(0, 12345, 0)).toEqual({ dx: 0, dy: 0 });
    });

    it('segue a fórmula senoidal documentada', () => {
        const t = 10000;
        const { dx, dy } = deriva(1, t, 1);

        expect(dx).toBeCloseTo(Math.sin(t * 0.00031 * 1.27 + 2.1) * 26, 10);
        expect(dy).toBeCloseTo(Math.cos(t * 0.00027 * 1.19 + 1.3) * 20, 10);
    });

    it('escala linearmente com o fator', () => {
        const cheio = deriva(2, 7000, 1);
        const meio = deriva(2, 7000, 0.5);

        expect(meio.dx).toBeCloseTo(cheio.dx / 2, 10);
        expect(meio.dy).toBeCloseTo(cheio.dy / 2, 10);
    });
});

describe('Tempos e cores', () => {
    it('a sequência de sucesso soma 2900 ms até a navegação', () => {
        expect(TEMPOS.preparing).toBe(900);
        expect(TEMPOS.docking).toBe(2000);
        expect(TEMPOS.navegacao).toBe(2900);
    });

    it('a recusa dura 1100 ms e o reconhecimento espera 520 ms', () => {
        expect(TEMPOS.denied).toBe(1100);
        expect(TEMPOS.debounceIdentificador).toBe(520);
    });

    it('docking é um estado de shell, ready não é', () => {
        expect(ESTADOS_SHELL).toContain('docking');
        expect(ESTADOS_SHELL).not.toContain('validating');
    });

    it('a recusa pinta o texto de vermelho e a autorização de azul claro', () => {
        expect(corDoEstado('denied')).toBe('oklch(78% 0.13 25)');
        expect(corDoEstado('authorized')).toBe('oklch(82% 0.1 250)');
        expect(corDoEstado('idle')).toBe('rgba(255,255,255,0.6)');
        expect(corDoEstado('ready')).toBe('rgba(255,255,255,0.88)');
    });
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `npx vitest run resources/js/pages/auth/gestao-login-scene-state.test.ts`
Expected: FAIL. `Failed to resolve import "./gestao-login-scene-state"`.

- [ ] **Step 3: Implementar o módulo**

Criar `resources/js/pages/auth/gestao-login-scene-state.ts`:

```ts
/**
 * Máquina de estados e matemática da cena de alinhamento do login de
 * gestão. Módulo puro, sem DOM e sem React: é aqui que mora tudo que
 * define para onde cada elemento da cena vai em cada estado da sessão.
 * O loop de animação (gestao-login-scene.tsx) só persegue estes alvos.
 */

export type FaseLogin = 'idle' | 'validating' | 'denied' | 'authorized' | 'preparing' | 'docking';

export type EstadoLogico = FaseLogin | 'session' | 'secure' | 'ready';

export type TipoConta = { rotulo: string; selo: string };

export type AlvosCena = Record<string, number>;

/** Fases em que o palco já virou sidebar: sem paralaxe, centro congelado. */
export const ESTADOS_SHELL: FaseLogin[] = ['docking'];

/** Estados em que os três anéis estão sobrepostos e os rótulos somem. */
export const ESTADOS_FUNDIDOS: EstadoLogico[] = ['validating', 'authorized', 'preparing', 'docking'];

export const TAXA_PADRAO = 3.2;

/**
 * Velocidade de perseguição de cada valor animado. Quanto maior, mais
 * rápido o valor alcança o alvo. Os que não aparecem aqui usam TAXA_PADRAO.
 */
export const TAXAS: Record<string, number> = {
    sec_rot: 2.2,
    disc_x: 3.4,
    disc_y: 3.4,
    disc_k: 3.4,
    ax_y: 9,
    ph_b: 2,
    drift: 1.5,
    ax_len: 3.6,
};

export const TEMPOS = {
    /** Piso do beat de validação: abaixo disso a fase colapsa em rede rápida. */
    pisoValidacao: 1450,
    preparing: 900,
    docking: 2000,
    navegacao: 2900,
    denied: 1100,
    debounceIdentificador: 520,
} as const;

export const TEXTOS_ESTADO: Record<EstadoLogico, { numero: string; texto: string }> = {
    idle: { numero: '01', texto: 'Central em espera' },
    session: { numero: '02', texto: 'Operador iniciou uma sessão' },
    secure: { numero: '02', texto: 'Camada de autorização em preparo' },
    ready: { numero: '02', texto: 'Pronto para iniciar a análise' },
    validating: { numero: '03', texto: 'Validando credencial' },
    denied: { numero: '03', texto: 'Credencial não confere' },
    authorized: { numero: '04', texto: 'Operador autorizado' },
    preparing: { numero: '05', texto: 'Preparando análise de viabilidade' },
    docking: { numero: '06', texto: 'Central de viabilidade aberta' },
};

export function corDoEstado(estado: EstadoLogico): string {
    if (estado === 'denied') {
        return 'oklch(78% 0.13 25)';
    }

    if (estado === 'authorized' || estado === 'preparing') {
        return 'oklch(82% 0.1 250)';
    }

    if (estado === 'idle') {
        return 'rgba(255,255,255,0.6)';
    }

    return 'rgba(255,255,255,0.88)';
}

export function classificarIdentificador(valor: string): TipoConta | null {
    const texto = (valor || '').trim();

    if (texto === '') {
        return null;
    }

    if (texto.includes('@')) {
        if (/@salvador\.ba\.gov\.br$/i.test(texto)) {
            return { rotulo: 'E-mail institucional', selo: 'E-mail institucional' };
        }

        if (/^[^@\s]+@[^@\s]+\.[^@\s]{2,}$/.test(texto)) {
            return { rotulo: 'Conta local', selo: 'Conta local' };
        }

        return null;
    }

    if (/^[a-z0-9._-]{3,}$/i.test(texto)) {
        return { rotulo: 'Rede corporativa (AD)', selo: 'Rede corporativa · AD' };
    }

    return null;
}

export function estadoLogico(entrada: {
    fase: FaseLogin;
    identificador: string;
    temSenha: boolean;
    comFoco: boolean;
    tipoConta: TipoConta | null;
}): EstadoLogico {
    if (entrada.fase !== 'idle') {
        return entrada.fase;
    }

    if (entrada.tipoConta && entrada.temSenha && entrada.identificador !== '') {
        return 'ready';
    }

    if (entrada.temSenha) {
        return 'secure';
    }

    if (entrada.comFoco || entrada.identificador !== '') {
        return 'session';
    }

    return 'idle';
}

/**
 * Vida dos anéis no estado de espera. `fator` vem do alvo do estado: 1 em
 * idle, 0 quando a cena precisa ficar imóvel.
 */
export function deriva(indice: number, tempoMs: number, fator: number): { dx: number; dy: number } {
    const dx = Math.sin(tempoMs * 0.00031 * (1 + indice * 0.27) + indice * 2.1) * 26 * fator;
    const dy = Math.cos(tempoMs * 0.00027 * (1 + indice * 0.19) + indice * 1.3) * 20 * fator;

    return { dx, dy };
}

type PosicaoAnel = [x: number, y: number, escala: number, blur: number, opacidade: number];

const SESSION: PosicaoAnel[] = [
    [-72, -40, 0.95, 1.2, 0.72],
    [68, -22, 1, 0, 0.84],
    [-4, 70, 0.9, 1.6, 0.64],
];

const CENTRO: PosicaoAnel[] = [
    [0, 0, 1, 0, 0.95],
    [0, 0, 1, 0, 0.95],
    [0, 0, 1, 0, 0.95],
];

function aplicarAneis(alvos: AlvosCena, posicoes: PosicaoAnel[]): void {
    ['a', 'b', 'c'].forEach((chave, indice) => {
        const [x, y, escala, blur, opacidade] = posicoes[indice];
        alvos[`${chave}_x`] = x;
        alvos[`${chave}_y`] = y;
        alvos[`${chave}_s`] = escala;
        alvos[`${chave}_b`] = blur;
        alvos[`${chave}_o`] = opacidade;
    });
}

/**
 * Alvos de todos os valores animados no estado informado.
 *
 * @param base - Centro da cena na viewport, em px.
 * @param rotacaoBase - Ângulo já acumulado pelo arco, para somar mais uma volta sem recuar.
 * @param alvoYLinha - Altura na viewport onde a linha de conexão encosta no painel.
 */
export function alvosPorEstado(
    estado: EstadoLogico,
    base: { x: number; y: number },
    rotacaoBase: number,
    alvoYLinha: number,
): AlvosCena {
    const alvos: AlvosCena = {
        drift: 1,
        rs: 1,
        sec_c: 0,
        sec_o: 0,
        sec_rot: rotacaoBase,
        sec_red: 0,
        disc_s: 0,
        disc_x: 0,
        disc_y: 0,
        disc_k: 1,
        disc_o: 1,
        ph_b: 5,
        ph_s: 1.02,
        ph_br: 0.74,
        ax_o: 0,
        ax_len: 0,
        ax_y: alvoYLinha,
    };

    switch (estado) {
        case 'idle':
            aplicarAneis(alvos, [
                [-130, -72, 0.9, 2.4, 0.46],
                [124, -36, 1, 0.6, 0.56],
                [-8, 120, 0.82, 3.2, 0.42],
            ]);
            break;

        case 'session':
            aplicarAneis(alvos, SESSION);
            Object.assign(alvos, { drift: 0.5, ph_b: 3.2, ph_br: 0.82, ax_o: 1, ax_len: 1 });
            break;

        case 'secure':
            aplicarAneis(
                alvos,
                SESSION.map(([x, y, escala, blur, opacidade]): PosicaoAnel => [
                    x * 0.72,
                    y * 0.72,
                    escala,
                    blur * 0.5,
                    opacidade + 0.08,
                ]),
            );
            Object.assign(alvos, { drift: 0.32, sec_o: 1, sec_c: 58, ph_b: 2.4, ph_br: 0.85, ax_o: 1, ax_len: 1 });
            break;

        case 'ready':
            aplicarAneis(alvos, [
                [-16, -9, 0.99, 0, 0.92],
                [16, -5, 1, 0, 0.92],
                [-2, 16, 0.98, 0, 0.9],
            ]);
            Object.assign(alvos, { drift: 0.12, sec_o: 1, sec_c: 86, ph_b: 1.4, ph_br: 0.9, ax_o: 1, ax_len: 1 });
            break;

        case 'validating':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 1,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                ph_b: 0.6,
                ph_br: 0.95,
                ax_o: 1,
                ax_len: 0,
            });
            break;

        case 'denied':
            aplicarAneis(alvos, [
                [-165, -96, 0.88, 2.2, 0.5],
                [162, -56, 1, 1, 0.56],
                [-20, 165, 0.84, 2.6, 0.46],
            ]);
            Object.assign(alvos, {
                drift: 1.3,
                sec_o: 1,
                sec_c: 16,
                sec_red: 1,
                sec_rot: rotacaoBase + 360,
                ph_b: 4,
                ph_br: 0.78,
            });
            break;

        case 'authorized':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 0.55,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                ph_b: 0,
                ph_br: 1.02,
            });
            break;

        case 'preparing':
            aplicarAneis(alvos, CENTRO);
            Object.assign(alvos, {
                drift: 0,
                sec_o: 0.4,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                ph_b: 0,
                ph_br: 1.04,
                ph_s: 1.05,
            });
            break;

        default:
            // docking: os anéis atravessam a lente enquanto o disco vira o
            // logo da sidebar do console, em (38, 50) na viewport.
            aplicarAneis(alvos, [
                [0, 0, 1, 4, 0],
                [0, 0, 1, 4, 0],
                [0, 0, 1, 4, 0],
            ]);
            Object.assign(alvos, {
                drift: 0,
                rs: 3.4,
                sec_o: 0,
                sec_c: 100,
                sec_rot: rotacaoBase + 360,
                disc_s: 1,
                disc_x: 38 - base.x,
                disc_y: 50 - base.y,
                disc_k: 0.3,
                disc_o: 1,
                ph_b: 7,
                ph_s: 1.12,
                ph_br: 0.9,
            });
    }

    return alvos;
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `npx vitest run resources/js/pages/auth/gestao-login-scene-state.test.ts`
Expected: PASS, 19 testes.

- [ ] **Step 5: Commitar**

```bash
git add resources/js/pages/auth/gestao-login-scene-state.ts resources/js/pages/auth/gestao-login-scene-state.test.ts
git commit -m "feat: adiciona máquina de estados da cena de alinhamento do login"
```

---

### Task 3: Palco em flex com camada de foto própria

**Files:**
- Modify: `resources/js/pages/auth/gestao-login-theme.ts:6-26`
- Modify: `resources/js/pages/auth/gestao-login-theme.test.ts`
- Modify: `resources/js/pages/auth/gestao-login.tsx` (remover `ZoneMap`, linhas 51-100 e a chamada na linha 105; remover o keyframe `sile-zone-pulse` na linha 224)

**Interfaces:**
- Consumes: nada.
- Produces (usado pelas Tasks 4 e 5):
  - `gestaoLoginShellClasses` — shell em flex, sem `grid-cols`
  - `gestaoLoginBrandStageClasses` — palco sem a foto no `background`, com `width` animável
  - `gestaoLoginStagePhotoClasses` — camada da foto, alvo do `filter` e do `transform` do loop
  - `gestaoLoginStageGradientClasses` — camada dos dois gradientes navy
  - `gestaoLoginStageVeilClasses` — véu `#0c111d` que aparece no docking

**Por que flex:** coluna `fr` de grid não interpola. Com `grid-cols-[minmax(0,1fr)_...]` a contração do palco para 290px salta em vez de animar.

- [ ] **Step 1: Escrever o teste falhando**

Em `resources/js/pages/auth/gestao-login-theme.test.ts`, trocar o import por:

```ts
import {
    gestaoLoginBrandStageClasses,
    gestaoLoginInputClasses,
    gestaoLoginLabelClasses,
    gestaoLoginPanelClasses,
    gestaoLoginShellClasses,
    gestaoLoginStageGradientClasses,
    gestaoLoginStagePhotoClasses,
    gestaoLoginStageVeilClasses,
} from './gestao-login-theme';
```

E acrescentar, antes do fechamento do `describe`:

```ts
    it('shell é flex para a largura do palco poder animar', () => {
        expect(gestaoLoginShellClasses).toContain('flex');
        expect(gestaoLoginShellClasses).not.toContain('grid-cols-[');
    });

    it('palco não carrega mais a foto no background e anima a largura', () => {
        expect(gestaoLoginBrandStageClasses).not.toContain('salvador-hero.jpg');
        expect(gestaoLoginBrandStageClasses).toContain('transition-[width,padding]');
    });

    it('foto ficou numa camada própria, que o loop consegue desfocar', () => {
        expect(gestaoLoginStagePhotoClasses).toContain('salvador-hero.jpg');
        expect(gestaoLoginStagePhotoClasses).toContain('-inset-[30px]');
        expect(gestaoLoginStagePhotoClasses).toContain('bg-cover');
    });

    it('gradientes e véu do console são camadas separadas acima da foto', () => {
        expect(gestaoLoginStageGradientClasses).toContain('linear-gradient');
        expect(gestaoLoginStageVeilClasses).toContain('bg-[#0c111d]');
        expect(gestaoLoginStageVeilClasses).toContain('opacity-0');
    });
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `npx vitest run resources/js/pages/auth/gestao-login-theme.test.ts`
Expected: FAIL. `gestaoLoginStagePhotoClasses is not exported`.

- [ ] **Step 3: Implementar o tema**

Em `resources/js/pages/auth/gestao-login-theme.ts`, substituir as linhas 6-26 por:

```ts
export const gestaoLoginBrandStageClasses = [
    'relative hidden min-h-0 shrink-0 flex-col overflow-hidden',
    gestaoLoginBrandForeground,
    'bg-[oklch(12.5%_0.03_255)]',
    'px-[clamp(28px,5vw,96px)] py-[clamp(28px,3.5vw,56px)]',
    'min-[1101px]:flex min-[1101px]:h-full',
    'min-[1101px]:w-[calc(100%-clamp(440px,34vw,580px))]',
    'transition-[width,padding] duration-[1.05s] ease-[cubic-bezier(0.7,0,0.2,1)]',
    // Docking: o palco vira a sidebar do console (290px, padding 32/20).
    'data-[docking=true]:min-[1101px]:w-[290px] data-[docking=true]:px-5 data-[docking=true]:py-8',
].join(' ');

/** Camada da foto: separada do palco para receber blur, brilho e escala animados. */
export const gestaoLoginStagePhotoClasses = [
    'pointer-events-none absolute -inset-[30px] z-0',
    "bg-[url('/images/salvador-hero.jpg')] bg-cover bg-center",
    'will-change-[filter,transform]',
].join(' ');

/** Os dois gradientes navy do palco, acima da foto. */
export const gestaoLoginStageGradientClasses = [
    'pointer-events-none absolute inset-0 z-0',
    'bg-[linear-gradient(180deg,oklch(12.5%_0.03_255_/_0.55)_0%,oklch(12.5%_0.03_255_/_0.2)_38%,oklch(12.5%_0.032_255_/_0.86)_100%),linear-gradient(100deg,oklch(12.5%_0.032_255_/_0.9)_0%,oklch(12.5%_0.03_255_/_0.42)_60%,oklch(12.5%_0.03_255_/_0.18)_100%)]',
].join(' ');

/** Véu do console: a cidade fica levemente visível sob a sidebar. */
export const gestaoLoginStageVeilClasses = [
    'pointer-events-none absolute inset-0 z-0 bg-[#0c111d] opacity-0',
    'transition-opacity duration-1000 ease-linear delay-[0.25s]',
    'data-[docking=true]:opacity-92',
].join(' ');

export const gestaoLoginShellClasses = [
    'flex h-dvh overflow-hidden overscroll-none bg-gray-50',
    "font-[-apple-system,BlinkMacSystemFont,'SF_Pro_Text','Segoe_UI',system-ui,sans-serif]",
    'text-gray-900 antialiased',
    'dark:bg-[oklch(15%_0.034_255)] dark:text-[oklch(96%_0.008_250)]',
].join(' ');
```

Em `gestaoLoginPanelClasses` (linhas 21-26 originais), trocar a primeira entrada para o painel ocupar o resto e poder trocar de fundo no docking:

```ts
export const gestaoLoginPanelClasses = [
    'flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden bg-white',
    'px-[clamp(22px,4vw,72px)] py-[clamp(20px,3.5vw,56px)]',
    'min-[1101px]:border-l min-[1101px]:border-gray-200',
    'dark:bg-[oklch(19%_0.038_255)] min-[1101px]:dark:border-white/8',
    'transition-[background-color,border-color] duration-600 ease-linear delay-300',
    'data-[docking=true]:border-transparent data-[docking=true]:bg-[#f9fafb]',
    'data-[docking=true]:dark:border-transparent data-[docking=true]:dark:bg-[#f9fafb]',
].join(' ');
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `npx vitest run resources/js/pages/auth/gestao-login-theme.test.ts`
Expected: PASS, 9 testes.

- [ ] **Step 5: Remover o ZoneMap do login**

Em `resources/js/pages/auth/gestao-login.tsx`:

1. Apagar a função `ZoneMap` inteira (linhas 51-100).
2. Apagar a linha `<ZoneMap />` dentro de `BrandStage` (linha 105).
3. No `<style>` do `<Head>` (linha 224), apagar a linha do keyframe `sile-zone-pulse`, deixando só:

```tsx
                <style>{`
                    @keyframes sile-rise { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: none; } }
                `}</style>
```

4. No início de `BrandStage`, montar as três camadas novas antes do `<header>`:

```tsx
function BrandStage({
    docking,
    stageRef,
    photoRef,
}: {
    docking: boolean;
    stageRef: RefObject<HTMLElement | null>;
    photoRef: RefObject<HTMLDivElement | null>;
}) {
    const marca = docking ? 'true' : 'false';

    return (
        <section ref={stageRef} className={gestaoLoginBrandStageClasses} data-docking={marca} aria-label="Apresentação do console">
            <div ref={photoRef} className={gestaoLoginStagePhotoClasses} aria-hidden="true" />
            <div className={gestaoLoginStageGradientClasses} aria-hidden="true" />
            <div className={gestaoLoginStageVeilClasses} data-docking={marca} aria-hidden="true" />
```

5. Ajustar os imports no topo do arquivo: acrescentar `RefObject` ao import de tipo do React e as três classes novas ao import de `./gestao-login-theme`.

6. No `GestaoLoginContent`, criar os refs e passar para o palco. Por enquanto `docking` é sempre `false` — a Task 5 liga isso na fase real:

```tsx
    const stageRef = useRef<HTMLElement>(null);
    const photoRef = useRef<HTMLDivElement>(null);
```

```tsx
            <BrandStage docking={false} stageRef={stageRef} photoRef={photoRef} />
```

- [ ] **Step 6: Verificar que o build passa e a tela ainda monta**

Run: `npx tsc --noEmit -p tsconfig.json && npm run build`
Expected: sem erro de tipo e build concluído.

- [ ] **Step 7: Commitar**

```bash
git add resources/js/pages/auth/gestao-login-theme.ts resources/js/pages/auth/gestao-login-theme.test.ts resources/js/pages/auth/gestao-login.tsx
git commit -m "refactor: palco do login vira flex com a foto em camada própria"
```

---

### Task 4: Cena dos anéis e o loop de animação

**Files:**
- Create: `resources/js/pages/auth/gestao-login-scene.tsx`

**Interfaces:**
- Consumes: de `gestao-login-scene-state.ts` — `alvosPorEstado`, `deriva`, `TAXAS`, `TAXA_PADRAO`, `TEXTOS_ESTADO`, `corDoEstado`, `ESTADOS_FUNDIDOS`, `ESTADOS_SHELL`, e os tipos `EstadoLogico`, `FaseLogin`, `TipoConta`, `AlvosCena`.
- Produces (usado pela Task 5):
  - `export default function GestaoLoginScene(props: GestaoLoginSceneProps)`
  - `type GestaoLoginSceneProps = { estado: EstadoLogico; fase: FaseLogin; tipoConta: TipoConta | null; photoRef: RefObject<HTMLDivElement | null>; alvoLinhaRef: RefObject<HTMLElement | null>; stageRef: RefObject<HTMLElement | null> }`

**Regras que o loop precisa respeitar:**
- Um único `requestAnimationFrame`. Nenhum `setState` por frame.
- `cur += (alvo − cur) × (1 − e^(−k·dt))`, com `dt` limitado a 0,05 s.
- Centro da cena: `x = largura do palco × 0,70`, `y = innerHeight × 0,47`. Recalcular no resize; congelar quando a fase estiver em `ESTADOS_SHELL`.
- Com `prefers-reduced-motion: reduce`, deriva e paralaxe zeram e o blur dos anéis e da foto vira 0.
- Abaixo de 1101px o componente não renderiza nada e o loop nem começa.

- [ ] **Step 1: Criar o componente**

Criar `resources/js/pages/auth/gestao-login-scene.tsx`:

```tsx
import { useEffect, useRef, useState } from 'react';
import type { RefObject } from 'react';
import { LogoMark } from '@/components/app/logo';
import {
    ESTADOS_FUNDIDOS,
    ESTADOS_SHELL,
    TAXA_PADRAO,
    TAXAS,
    TEXTOS_ESTADO,
    alvosPorEstado,
    corDoEstado,
    deriva,
} from './gestao-login-scene-state';
import type { AlvosCena, EstadoLogico, FaseLogin, TipoConta } from './gestao-login-scene-state';
import { gestaoLoginMonoFont } from './gestao-login-theme';

const AZUL = 'oklch(64% 0.155 250)';
const VERMELHO = 'oklch(64% 0.19 25)';
const TILE = 'oklch(47% 0.135 252)';
const LARGURA_MINIMA_PALCO = 1101;

export type GestaoLoginSceneProps = {
    estado: EstadoLogico;
    fase: FaseLogin;
    tipoConta: TipoConta | null;
    /** Camada da foto do palco: recebe blur, brilho e escala do loop. */
    photoRef: RefObject<HTMLDivElement | null>;
    /** Elemento do formulário onde a linha de conexão encosta. */
    alvoLinhaRef: RefObject<HTMLElement | null>;
    stageRef: RefObject<HTMLElement | null>;
};

const rotuloClasses = 'grid gap-1.5 text-[10px] leading-none font-medium tracking-[0.1em] whitespace-nowrap uppercase';

export default function GestaoLoginScene({ estado, fase, tipoConta, photoRef, alvoLinhaRef, stageRef }: GestaoLoginSceneProps) {
    const [amplo, setAmplo] = useState(() => typeof window !== 'undefined' && window.innerWidth >= LARGURA_MINIMA_PALCO);

    const scene = useRef<HTMLDivElement>(null);
    const anelA = useRef<HTMLDivElement>(null);
    const anelB = useRef<HTMLDivElement>(null);
    const anelC = useRef<HTMLDivElement>(null);
    const arcoWrap = useRef<HTMLDivElement>(null);
    const arco = useRef<SVGCircleElement>(null);
    const disco = useRef<HTMLDivElement>(null);
    const linha = useRef<SVGPathElement>(null);
    const noInicial = useRef<SVGCircleElement>(null);
    const noFinal = useRef<SVGCircleElement>(null);

    // Valores lidos pelo loop sem provocar re-render.
    const estadoRef = useRef(estado);
    const faseRef = useRef(fase);
    estadoRef.current = estado;
    faseRef.current = fase;

    const centro = useRef({ x: 0, y: 0 });
    const atual = useRef<AlvosCena | null>(null);
    const rotacaoBase = useRef(0);
    const mouse = useRef({ x: 0, y: 0 });
    const blurAnterior = useRef<number[]>([-1, -1, -1]);
    const fotoAnterior = useRef('');

    useEffect(() => {
        const onResize = () => setAmplo(window.innerWidth >= LARGURA_MINIMA_PALCO);
        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    // Cada entrada em validating ou denied soma uma volta ao arco. A base
    // acumulada evita que a próxima volta recue o ângulo já percorrido.
    useEffect(() => {
        if (fase === 'validating' || fase === 'denied') {
            rotacaoBase.current = Math.round((atual.current?.sec_rot ?? 0) / 360) * 360;
        }
    }, [fase]);

    useEffect(() => {
        if (!amplo) {
            return;
        }

        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const medir = () => {
            const palco = stageRef.current;

            if (!palco || ESTADOS_SHELL.includes(faseRef.current)) {
                return;
            }

            centro.current = {
                x: palco.getBoundingClientRect().width * 0.7,
                y: window.innerHeight * 0.47,
            };
        };

        const onMove = (evento: MouseEvent) => {
            const palco = stageRef.current;

            if (!palco || reduzido) {
                return;
            }

            const caixa = palco.getBoundingClientRect();
            mouse.current = {
                x: (evento.clientX - caixa.left) / caixa.width - 0.5,
                y: (evento.clientY - caixa.top) / caixa.height - 0.5,
            };
        };

        const onLeave = () => {
            mouse.current = { x: 0, y: 0 };
        };

        medir();
        window.addEventListener('resize', medir);
        const palco = stageRef.current;
        palco?.addEventListener('mousemove', onMove);
        palco?.addEventListener('mouseleave', onLeave);

        arco.current?.setAttribute('pathLength', '100');
        linha.current?.setAttribute('pathLength', '1');

        let anterior = performance.now();
        let quadro = 0;

        const alturaAlvoLinha = () => {
            const alvo = alvoLinhaRef.current;

            if (!alvo) {
                return window.innerHeight / 2;
            }

            const caixa = alvo.getBoundingClientRect();

            return caixa.top + caixa.height / 2;
        };

        const frame = (agora: number) => {
            const dt = Math.min(0.05, (agora - anterior) / 1000);
            anterior = agora;

            if (!ESTADOS_SHELL.includes(faseRef.current)) {
                medir();
            }

            const base = centro.current;
            const alvos = alvosPorEstado(estadoRef.current, base, rotacaoBase.current, alturaAlvoLinha());

            if (!atual.current) {
                atual.current = { ...alvos };
            }

            const cur = atual.current;

            for (const chave in alvos) {
                const taxa = TAXAS[chave] ?? TAXA_PADRAO;
                cur[chave] += (alvos[chave] - cur[chave]) * (1 - Math.exp(-taxa * dt));
            }

            const noShell = ESTADOS_SHELL.includes(faseRef.current);
            const mx = noShell || reduzido ? 0 : mouse.current.x;
            const my = noShell || reduzido ? 0 : mouse.current.y;

            if (scene.current) {
                scene.current.style.transform = `translate(${base.x.toFixed(1)}px, ${base.y.toFixed(1)}px)`;
            }

            [anelA, anelB, anelC].forEach((ref, indice) => {
                const el = ref.current;

                if (!el) {
                    return;
                }

                const chave = ['a', 'b', 'c'][indice];
                const { dx, dy } = reduzido ? { dx: 0, dy: 0 } : deriva(indice, agora, cur.drift);
                const x = cur[`${chave}_x`] + dx - mx * (10 + indice * 9);
                const y = cur[`${chave}_y`] + dy - my * (8 + indice * 7);

                el.style.transform = `translate3d(${x.toFixed(2)}px, ${y.toFixed(2)}px, 0) scale(${(cur[`${chave}_s`] * cur.rs).toFixed(4)})`;
                el.style.opacity = Math.max(0, cur[`${chave}_o`]).toFixed(3);

                const blur = reduzido ? 0 : Math.max(0, cur[`${chave}_b`]);

                if (Math.abs(blur - blurAnterior.current[indice]) > 0.04) {
                    el.style.filter = blur > 0.05 ? `blur(${blur.toFixed(2)}px)` : 'none';
                    blurAnterior.current[indice] = blur;
                }
            });

            if (arcoWrap.current) {
                arcoWrap.current.style.transform = `rotate(${cur.sec_rot.toFixed(2)}deg) scale(${cur.rs.toFixed(4)})`;
                arcoWrap.current.style.opacity = Math.max(0, cur.sec_o).toFixed(3);
            }

            if (arco.current) {
                const preenchido = Math.max(0, Math.min(100, cur.sec_c));
                arco.current.setAttribute('stroke-dasharray', `${preenchido.toFixed(2)} ${(100 - preenchido + 0.01).toFixed(2)}`);
                arco.current.style.stroke = cur.sec_red > 0.5 ? VERMELHO : AZUL;
            }

            if (disco.current) {
                disco.current.style.transform = `translate3d(${cur.disc_x.toFixed(2)}px, ${cur.disc_y.toFixed(2)}px, 0) scale(${Math.max(0, cur.disc_s * cur.disc_k).toFixed(4)})`;
                disco.current.style.opacity = Math.max(0, Math.min(1, cur.disc_o)).toFixed(3);
            }

            if (photoRef.current) {
                const blur = reduzido ? 0 : Math.max(0, cur.ph_b);
                const assinatura = blur.toFixed(2) + cur.ph_br.toFixed(3);

                if (assinatura !== fotoAnterior.current) {
                    photoRef.current.style.filter = `blur(${blur.toFixed(2)}px) brightness(${cur.ph_br.toFixed(3)})`;
                    fotoAnterior.current = assinatura;
                }

                photoRef.current.style.transform = `translate(${(mx * -8).toFixed(1)}px, ${(my * -6).toFixed(1)}px) scale(${cur.ph_s.toFixed(4)})`;
            }

            const palcoAtual = stageRef.current;

            if (palcoAtual && linha.current) {
                const direita = palcoAtual.getBoundingClientRect().right;
                const x0 = base.x + 152;
                const y0 = base.y;
                const x2 = direita - 40;
                const meio = x0 + (x2 - x0) * 0.42;
                const alcanca = x2 > x0 + 20;

                linha.current.setAttribute(
                    'd',
                    `M ${x0.toFixed(1)} ${y0.toFixed(1)} L ${meio.toFixed(1)} ${y0.toFixed(1)} L ${x2.toFixed(1)} ${cur.ax_y.toFixed(1)} L ${direita.toFixed(1)} ${cur.ax_y.toFixed(1)}`,
                );

                const comprimento = Math.max(0, Math.min(1, cur.ax_len));
                linha.current.setAttribute('stroke-dasharray', `${comprimento.toFixed(4)} 1`);

                const opacidade = alcanca ? Math.max(0, cur.ax_o) : 0;
                linha.current.style.opacity = opacidade.toFixed(3);

                if (noInicial.current) {
                    noInicial.current.setAttribute('cx', x0.toFixed(1));
                    noInicial.current.setAttribute('cy', y0.toFixed(1));
                    noInicial.current.style.opacity = (opacidade * Math.min(1, comprimento * 4)).toFixed(3);
                }

                if (noFinal.current) {
                    noFinal.current.setAttribute('cx', direita.toFixed(1));
                    noFinal.current.setAttribute('cy', cur.ax_y.toFixed(1));
                    noFinal.current.style.opacity = (opacidade * Math.max(0, (comprimento - 0.9) * 10)).toFixed(3);
                }
            }

            quadro = requestAnimationFrame(frame);
        };

        quadro = requestAnimationFrame(frame);

        return () => {
            cancelAnimationFrame(quadro);
            window.removeEventListener('resize', medir);
            palco?.removeEventListener('mousemove', onMove);
            palco?.removeEventListener('mouseleave', onLeave);
        };
    }, [amplo, alvoLinhaRef, photoRef, stageRef]);

    if (!amplo) {
        return null;
    }

    const fundido = ESTADOS_FUNDIDOS.includes(estado);
    const noShell = ESTADOS_SHELL.includes(fase);
    const { numero, texto } = TEXTOS_ESTADO[estado];
    const cor = corDoEstado(estado);
    const mostraOperador = tipoConta !== null && ['session', 'secure', 'ready', 'validating', 'authorized'].includes(estado);

    return (
        <div aria-hidden="true" className="pointer-events-none absolute inset-0 z-6">
            <svg className="absolute inset-0 size-full overflow-visible">
                <path ref={linha} d="M0 0" fill="none" stroke="oklch(64% 0.155 250 / 0.85)" strokeWidth="1" style={{ opacity: 0 }} />
                <circle ref={noInicial} cx="0" cy="0" r="2.5" fill={AZUL} style={{ opacity: 0 }} />
                <circle ref={noFinal} cx="0" cy="0" r="4" fill={TILE} stroke="oklch(80% 0.09 250)" strokeWidth="1" style={{ opacity: 0 }} />
            </svg>

            <div ref={scene} className="absolute top-0 left-0 size-0">
                {/* Linha de estado e linha do operador, acima da cena */}
                <div className="absolute -top-[268px] -left-[220px] grid w-[440px] justify-items-center gap-2.5">
                    <div
                        key={estado + texto}
                        className={`${gestaoLoginMonoFont} flex items-center gap-2.5 text-[10.5px] font-medium tracking-[0.1em] whitespace-nowrap uppercase motion-safe:animate-[sile-rise_0.55s_cubic-bezier(0.2,0.7,0.2,1)_both]`}
                        style={{ color: cor }}
                    >
                        <span className="tabular-nums text-[oklch(50%_0.02_250)]">{numero} / 06</span>
                        <span className="h-px w-[18px] opacity-70" style={{ background: cor }} />
                        <span>{texto}</span>
                    </div>
                    <span
                        className={`${gestaoLoginMonoFont} text-[10px] tracking-[0.08em] text-[oklch(67%_0.025_250)] uppercase transition-all duration-500`}
                        style={{ opacity: mostraOperador ? 1 : 0, transform: mostraOperador ? 'none' : 'translateY(-4px)' }}
                    >
                        {tipoConta ? `Operador identificado · ${tipoConta.rotulo}` : ''}
                    </span>
                </div>

                {/* Anel de autorização */}
                <div ref={arcoWrap} className="absolute -top-[165px] -left-[165px] size-[330px] opacity-0">
                    <svg viewBox="0 0 330 330" className="absolute inset-0 size-full overflow-visible">
                        <circle cx="165" cy="165" r="160" fill="none" stroke="rgba(255,255,255,0.08)" strokeWidth="1" />
                        <circle
                            ref={arco}
                            cx="165"
                            cy="165"
                            r="160"
                            fill="none"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeDasharray="0 100"
                            transform="rotate(-90 165 165)"
                            style={{ stroke: AZUL, transition: 'stroke 0.3s' }}
                        />
                    </svg>
                </div>

                {/* Anel A — atividade empresarial */}
                <div ref={anelA} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute inset-0 rounded-full bg-[repeating-conic-gradient(rgba(255,255,255,0.6)_0deg_0.6deg,transparent_0.6deg_10deg)] [mask:radial-gradient(circle,transparent_114px,#000_115px,#000_124px,transparent_125px)]" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-1.5 right-[calc(100%-26px)] text-right transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Atividade empresarial</span>
                        <span className="text-white/42">CNAE</span>
                    </div>
                </div>

                {/* Anel B — localização */}
                <div ref={anelB} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute top-1/2 -right-[26px] -left-[26px] h-px bg-white/30" />
                    <div className="absolute -top-[26px] -bottom-[26px] left-1/2 w-px bg-white/30" />
                    <div className="absolute top-1/2 left-1/2 -mt-1.5 -ml-1.5 size-3 rounded-full border border-white/70" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-7 left-[calc(100%-18px)] transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Localização</span>
                        <span className="text-white/42">12°58′S · 38°30′W</span>
                    </div>
                </div>

                {/* Anel C — regras urbanísticas */}
                <div ref={anelC} className="absolute -top-[125px] -left-[125px] size-[250px] will-change-[transform,opacity,filter]">
                    <div className="absolute inset-0 rounded-full border border-white/40" />
                    <div className="absolute inset-4 rounded-full border border-dashed border-white/30" />
                    <div
                        className={`${gestaoLoginMonoFont} ${rotuloClasses} absolute top-[calc(100%+12px)] left-1/2 -translate-x-1/2 text-center transition-opacity duration-500`}
                        style={{ opacity: fundido ? 0 : 1 }}
                    >
                        <span className="text-white/72">Regras urbanísticas · LOUOS</span>
                        <span className="text-white/42">Lei nº 9.148/2016</span>
                    </div>
                </div>

                {/* Abertura: o disco que vira o logo da sidebar */}
                <div
                    ref={disco}
                    className="absolute -top-[125px] -left-[125px] grid size-[250px] scale-0 place-items-center rounded-full transition-[background-color,box-shadow] duration-600"
                    style={{
                        background: noShell ? 'transparent' : TILE,
                        boxShadow:
                            estado === 'authorized' || estado === 'preparing'
                                ? '0 0 0 1px rgba(255,255,255,0.18), 0 0 90px oklch(64% 0.155 250 / 0.5)'
                                : '0 0 0 0 transparent',
                    }}
                >
                    {/* LogoMark só aceita className: a cor vai no wrapper, por currentColor. */}
                    <span
                        className="flex transition-colors duration-600"
                        style={{ color: noShell ? '#5992ed' : '#ffffff' }}
                    >
                        <LogoMark className="size-[120px]" />
                    </span>
                </div>

                {/* Fórmula do produto */}
                <div
                    className={`${gestaoLoginMonoFont} absolute top-[190px] -left-[240px] flex w-[480px] items-center justify-center gap-2.5 text-[11px] tracking-[0.1em] text-white/78 uppercase transition-all duration-500`}
                    style={{
                        opacity: estado === 'preparing' ? 1 : 0,
                        transform: estado === 'preparing' ? 'none' : estado === 'docking' ? 'translateY(-10px)' : 'translateY(10px)',
                    }}
                >
                    <span>Atividade</span>
                    <span style={{ color: AZUL }}>+</span>
                    <span>Localização</span>
                    <span style={{ color: AZUL }}>+</span>
                    <span>LOUOS</span>
                    <span style={{ color: AZUL }}>→</span>
                    <span className="font-semibold text-white">Viabilidade</span>
                </div>
            </div>
        </div>
    );
}
```

- [ ] **Step 2: Verificar tipos**

Run: `npx tsc --noEmit -p tsconfig.json`
Expected: sem erros.

- [ ] **Step 3: Commitar**

```bash
git add resources/js/pages/auth/gestao-login-scene.tsx
git commit -m "feat: adiciona a cena de alinhamento dos anéis no login do console"
```

---

### Task 5: Orquestração do envio e ligação no login

**Files:**
- Create: `resources/js/pages/auth/gestao-login-docking.ts`
- Test: `resources/js/pages/auth/gestao-login-docking.test.ts`
- Modify: `resources/js/pages/auth/gestao-login.tsx`

**Interfaces:**
- Consumes: `TEMPOS` e `FaseLogin` de `gestao-login-scene-state.ts`; `GestaoLoginScene` da Task 4; o contrato `{destino}` da Task 1; `marcarEntradaConsole` da Task 6.
- Produces: `sequenciaDeEntrada(opcoes): () => void` — agenda as fases pós-sucesso e devolve a função de cancelamento.

**Ordem de execução:** esta tarefa depende da Task 6 (`marcarEntradaConsole`). Se executar antes, crie primeiro a Task 6.

- [ ] **Step 1: Escrever o teste falhando**

Criar `resources/js/pages/auth/gestao-login-docking.test.ts`:

```ts
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { sequenciaDeEntrada } from './gestao-login-docking';

describe('Sequência de entrada no console', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('percorre authorized, preparing e docking antes de navegar', () => {
        const fases: string[] = [];
        const navegar = vi.fn();

        sequenciaDeEntrada({
            destino: '/gestao',
            reduzido: false,
            aoMudarFase: (fase) => fases.push(fase),
            navegar,
        });

        expect(fases).toEqual(['authorized']);

        vi.advanceTimersByTime(900);
        expect(fases).toEqual(['authorized', 'preparing']);

        vi.advanceTimersByTime(1100);
        expect(fases).toEqual(['authorized', 'preparing', 'docking']);
        expect(navegar).not.toHaveBeenCalled();

        vi.advanceTimersByTime(900);
        expect(navegar).toHaveBeenCalledWith('/gestao');
    });

    it('com movimento reduzido navega na hora, sem passar pelas fases', () => {
        const fases: string[] = [];
        const navegar = vi.fn();

        sequenciaDeEntrada({
            destino: '/gestao/parametros',
            reduzido: true,
            aoMudarFase: (fase) => fases.push(fase),
            navegar,
        });

        expect(navegar).toHaveBeenCalledWith('/gestao/parametros');
        expect(fases).toEqual([]);
    });

    it('cancelar interrompe a sequência e não navega', () => {
        const navegar = vi.fn();

        const cancelar = sequenciaDeEntrada({
            destino: '/gestao',
            reduzido: false,
            aoMudarFase: () => {},
            navegar,
        });

        vi.advanceTimersByTime(900);
        cancelar();
        vi.advanceTimersByTime(5000);

        expect(navegar).not.toHaveBeenCalled();
    });
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `npx vitest run resources/js/pages/auth/gestao-login-docking.test.ts`
Expected: FAIL. `Failed to resolve import "./gestao-login-docking"`.

- [ ] **Step 3: Implementar a orquestração**

Criar `resources/js/pages/auth/gestao-login-docking.ts`:

```ts
import { marcarEntradaConsole } from '@/components/app/console-entrance';
import { TEMPOS } from './gestao-login-scene-state';
import type { FaseLogin } from './gestao-login-scene-state';

export type OpcoesSequencia = {
    destino: string;
    /** `prefers-reduced-motion: reduce` ativo: navega direto, sem o voo da marca. */
    reduzido: boolean;
    aoMudarFase: (fase: FaseLogin) => void;
    navegar: (destino: string) => void;
};

/**
 * Agenda as fases entre o "credencial aceita" e a troca de página. A
 * marca de entrada é gravada junto com a navegação: é ela que faz o
 * console animar a chegada uma vez só.
 *
 * @returns Função que cancela o que ainda não rodou.
 */
export function sequenciaDeEntrada({ destino, reduzido, aoMudarFase, navegar }: OpcoesSequencia): () => void {
    if (reduzido) {
        navegar(destino);

        return () => {};
    }

    const agendados: ReturnType<typeof setTimeout>[] = [];
    const agendar = (acao: () => void, atraso: number) => {
        agendados.push(setTimeout(acao, atraso));
    };

    aoMudarFase('authorized');
    agendar(() => aoMudarFase('preparing'), TEMPOS.preparing);
    agendar(() => aoMudarFase('docking'), TEMPOS.docking);
    agendar(() => {
        marcarEntradaConsole();
        navegar(destino);
    }, TEMPOS.navegacao);

    return () => agendados.forEach(clearTimeout);
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `npx vitest run resources/js/pages/auth/gestao-login-docking.test.ts`
Expected: PASS, 3 testes.

- [ ] **Step 5: Ligar tudo no `gestao-login.tsx`**

Substituir o bloco `<Form action="/gestao/login" method="post">…</Form>` (linhas 257-355 do arquivo original) por um formulário controlado com `useHttp`, e acrescentar a cena ao palco. As mudanças, em ordem:

1. Trocar os imports do topo:

```tsx
import { Head, Link, router, useHttp, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { FormEvent, KeyboardEvent, ReactNode, RefObject } from 'react';
```

(`FormEvent` tipa o `enviar`, `ReactNode` tipa o `children` do `BrandStage`, `RefObject` tipa os refs passados para a cena.)

e acrescentar:

```tsx
import { ArrowRightIcon, CheckCircleIcon, EyeCloseIcon, EyeIcon, MoonIcon, SunIcon } from '@/components/icons';
import { sequenciaDeEntrada } from './gestao-login-docking';
import GestaoLoginScene from './gestao-login-scene';
import { TEMPOS, classificarIdentificador, estadoLogico } from './gestao-login-scene-state';
import type { FaseLogin, TipoConta } from './gestao-login-scene-state';
```

2. Dentro de `GestaoLoginContent`, o estado e a submissão:

```tsx
    const [fase, setFase] = useState<FaseLogin>('idle');
    const [tipoConta, setTipoConta] = useState<TipoConta | null>(null);
    const [campoComFoco, setCampoComFoco] = useState<'ident' | 'senha' | null>(null);
    const [erroLogin, setErroLogin] = useState<string | null>(null);
    const [shakeX, setShakeX] = useState(0);

    const stageRef = useRef<HTMLElement>(null);
    const photoRef = useRef<HTMLDivElement>(null);
    const identRef = useRef<HTMLInputElement>(null);
    const senhaRef = useRef<HTMLInputElement>(null);
    const botaoRef = useRef<HTMLButtonElement>(null);
    const debounce = useRef<ReturnType<typeof setTimeout>>(undefined);
    const cancelarSequencia = useRef<() => void>(() => {});

    const form = useHttp<{ identifier: string; password: string; remember: boolean }, { destino: string }>({
        identifier: '',
        password: '',
        remember: true,
    });

    const estado = estadoLogico({
        fase,
        identificador: form.data.identifier.trim(),
        temSenha: form.data.password !== '',
        comFoco: campoComFoco !== null,
        tipoConta,
    });

    // A linha de conexão encosta no campo em foco; sem foco, no que falta
    // preencher — e no botão quando tudo já está pronto para enviar.
    const alvoLinhaRef = (
        campoComFoco === 'senha'
            ? senhaRef
            : campoComFoco === 'ident'
              ? identRef
              : estado === 'ready'
                ? botaoRef
                : form.data.password !== ''
                  ? senhaRef
                  : identRef
    ) as RefObject<HTMLElement | null>;

    useEffect(() => () => {
        clearTimeout(debounce.current);
        cancelarSequencia.current();
    }, []);

    const sacudir = () => {
        [10, -8, 6, -4, 2, 0].forEach((x, indice) => {
            setTimeout(() => setShakeX(x), indice * 70);
        });
    };

    const aoDigitarIdentificador = (valor: string) => {
        form.setData('identifier', valor);
        setErroLogin(null);
        clearTimeout(debounce.current);
        debounce.current = setTimeout(
            () => setTipoConta(classificarIdentificador(valor)),
            TEMPOS.debounceIdentificador,
        );
    };

    const enviar = (evento: FormEvent) => {
        evento.preventDefault();

        if (fase !== 'idle') {
            return;
        }

        clearTimeout(debounce.current);
        setErroLogin(null);
        setFase('validating');
        setTipoConta(classificarIdentificador(form.data.identifier) ?? { rotulo: 'Conta local', selo: 'Conta local' });

        const inicio = performance.now();
        const reduzido = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // Piso do beat de validação: em rede rápida a fase colapsaria e a
        // cena pularia direto para o disco.
        const comPiso = (acao: () => void) => {
            const restante = Math.max(0, TEMPOS.pisoValidacao - (performance.now() - inicio));
            setTimeout(acao, reduzido ? 0 : restante);
        };

        form.post('/gestao/login', {
            headers: { 'X-Viabiliza-Login-Async': '1' },
            onSuccess: (resposta) => {
                comPiso(() => {
                    cancelarSequencia.current = sequenciaDeEntrada({
                        destino: resposta.destino,
                        reduzido,
                        aoMudarFase: setFase,
                        navegar: (destino) => router.visit(destino, { preserveScroll: false }),
                    });
                });
            },
            onError: (erros) => {
                comPiso(() => {
                    setFase('denied');
                    form.setData('password', '');
                    sacudir();
                    setTimeout(() => {
                        setFase('idle');
                        setErroLogin(
                            (erros.identifier as string | undefined) ??
                                'Credenciais não conferem. A tentativa foi registrada para fins de auditoria.',
                        );
                    }, TEMPOS.denied);
                });
            },
        });
    };

    const rotuloBotao = useMemo(() => {
        if (fase === 'validating') return 'Validando credencial…';
        if (fase === 'denied') return 'Credencial não confere';
        if (fase === 'authorized') return 'Operador autorizado';
        if (fase !== 'idle') return 'Abrindo a central…';
        if (erroLogin) return 'Tentar novamente';

        return 'Entrar no console';
    }, [fase, erroLogin]);
```

3. O palco recebe a cena e a marca de docking:

```tsx
            <BrandStage
                docking={fase === 'docking'}
                stageRef={stageRef}
                photoRef={photoRef}
                selo={tipoConta}
                emRepouso={estado === 'idle'}
            >
                <GestaoLoginScene
                    estado={estado}
                    fase={fase}
                    tipoConta={tipoConta}
                    photoRef={photoRef}
                    alvoLinhaRef={alvoLinhaRef}
                    stageRef={stageRef}
                />
            </BrandStage>
```

4. Em `BrandStage`, além das camadas da Task 3, o selo passa a expandir e os blocos somem no docking. A assinatura vira:

```tsx
function BrandStage({
    docking,
    stageRef,
    photoRef,
    selo,
    emRepouso,
    children,
}: {
    docking: boolean;
    stageRef: RefObject<HTMLElement | null>;
    photoRef: RefObject<HTMLDivElement | null>;
    selo: TipoConta | null;
    emRepouso: boolean;
    children: ReactNode;
}) {
```

O `<section>` recebe `ref={stageRef}`. O selo "Ambiente interno" recebe, depois do texto:

```tsx
                    <span
                        className="inline-block overflow-hidden whitespace-nowrap text-[oklch(80%_0.09_250)] transition-all duration-700 ease-[cubic-bezier(0.2,0.7,0.2,1)]"
                        style={{ maxWidth: selo && !docking ? '240px' : '0px', opacity: selo && !docking ? 1 : 0 }}
                    >
                        {selo ? ` · ${selo.selo}` : ''}
                    </span>
```

e a borda do selo passa a ser dinâmica:

```tsx
                    style={{ borderColor: emRepouso ? 'rgba(255,255,255,0.08)' : 'oklch(64% 0.155 250 / 0.35)' }}
```

O bloco da headline e o `<dl>` do rodapé ganham o mesmo sufixo de classe, concatenado ao que já têm. Na ida o desaparecimento é imediato; na volta espera 0,9 s para não competir com a expansão do palco:

```tsx
const saidaDoPalco = docking
    ? 'translate-y-[-10px] opacity-0 transition-all duration-400'
    : 'translate-y-0 opacity-100 transition-all duration-400 delay-[0.9s]';
```

Headline (linha 129 do arquivo original):

```tsx
            <div className={`relative z-1 flex min-h-0 max-w-[720px] flex-1 flex-col justify-center py-[clamp(16px,4vh,48px)] motion-safe:animate-[sile-rise_0.6s_0.12s_cubic-bezier(0.2,0.7,0.2,1)_both] ${saidaDoPalco}`}>
```

Rodapé (linha 149 do arquivo original):

```tsx
            <dl className={`relative z-1 flex shrink-0 items-end justify-between gap-6 border-t border-white/8 pt-[clamp(16px,2.5vh,24px)] motion-safe:animate-[sile-rise_0.6s_0.22s_cubic-bezier(0.2,0.7,0.2,1)_both] ${saidaDoPalco}`}>
```

Header do palco (linha 107 do arquivo original) recebe só a opacidade, porque o selo continua ancorado nele:

```tsx
            <header className={`relative z-1 flex shrink-0 items-center justify-between gap-4 transition-opacity duration-400 ${docking ? 'opacity-0' : 'opacity-100 delay-[0.9s]'}`}>
```

E `{children}` entra como último filho do `<section>`.

5. O painel recebe `data-docking` e o conteúdo do formulário some no docking:

```tsx
            <section className={gestaoLoginPanelClasses} data-docking={fase === 'docking' ? 'true' : 'false'} aria-labelledby="panel-title">
                <div
                    className="flex min-h-0 flex-1 flex-col transition-all duration-350"
                    style={{
                        opacity: fase === 'docking' ? 0 : 1,
                        transform: fase === 'docking' ? 'translateY(-8px)' : 'none',
                        pointerEvents: fase === 'docking' ? 'none' : 'auto',
                    }}
                >
```

6. O `<form>` substitui o `<Form>` do Inertia:

```tsx
                    <form onSubmit={enviar} noValidate style={{ transform: `translateX(${shakeX}px)`, transition: 'transform 0.07s ease' }}>
```

Os campos passam a ser controlados. Identificador:

```tsx
                                    <input
                                        ref={identRef}
                                        id="identifier"
                                        type="text"
                                        name="identifier"
                                        autoComplete="username"
                                        autoFocus
                                        required
                                        placeholder="nome@salvador.ba.gov.br ou seuusuarioad"
                                        aria-describedby="identifier-help"
                                        aria-invalid={erroLogin ? 'true' : undefined}
                                        className={gestaoLoginInputClasses}
                                        value={form.data.identifier}
                                        onChange={(evento) => aoDigitarIdentificador(evento.target.value)}
                                        onFocus={() => setCampoComFoco('ident')}
                                        onBlur={() => {
                                            setCampoComFoco(null);
                                            clearTimeout(debounce.current);
                                            setTipoConta(classificarIdentificador(form.data.identifier));
                                        }}
                                    />
```

Senha:

```tsx
                                        <input
                                            ref={senhaRef}
                                            id="password"
                                            type={showPassword ? 'text' : 'password'}
                                            name="password"
                                            autoComplete="current-password"
                                            required
                                            onKeyUp={handlePasswordKeyUp}
                                            aria-invalid={erroLogin ? 'true' : undefined}
                                            className={`${gestaoLoginInputClasses} pr-14`}
                                            value={form.data.password}
                                            onChange={(evento) => {
                                                form.setData('password', evento.target.value);
                                                setErroLogin(null);
                                            }}
                                            onFocus={() => setCampoComFoco('senha')}
                                            onBlur={() => {
                                                setCampoComFoco(null);
                                                setCapsLockOn(false);
                                            }}
                                        />
```

"Manter conectado":

```tsx
                                    <input
                                        id="remember"
                                        name="remember"
                                        type="checkbox"
                                        checked={form.data.remember}
                                        onChange={(evento) => form.setData('remember', evento.target.checked)}
                                        className="size-4 cursor-pointer accent-brand-600 dark:accent-[oklch(47%_0.135_252)]"
                                    />
```

Botão:

```tsx
                                <button
                                    ref={botaoRef}
                                    type="submit"
                                    disabled={fase !== 'idle'}
                                    className={gestaoLoginSubmitClasses}
                                >
```

Conteúdo do botão:

```tsx
                                    {rotuloBotao}
                                    {fase === 'idle' && (
                                        <span className="transition-transform duration-150 group-hover:translate-x-[3px]" aria-hidden="true">
                                            <ArrowRightIcon className="size-[18px]" />
                                        </span>
                                    )}
                                    {(fase === 'authorized' || fase === 'preparing' || fase === 'docking') && (
                                        <CheckCircleIcon className="size-[18px]" aria-hidden="true" />
                                    )}
```

7. A caixa de erro passa a ler `erroLogin` em vez de `flash.error`, mantendo o `flash.error` como fallback da rota sem JavaScript:

```tsx
                    {(erroLogin ?? flash.error) && (
                        <p className="mt-6 rounded-xl border border-error-500/35 bg-error-50 px-4 py-3 text-sm text-error-600 dark:border-[oklch(64%_0.19_25_/_0.35)] dark:bg-[oklch(64%_0.19_25_/_0.1)] dark:text-[oklch(80%_0.12_25)]">
                            {erroLogin ?? flash.error}
                        </p>
                    )}
```

E o campo de senha recebe `aria-invalid={erroLogin ? 'true' : undefined}`.

- [ ] **Step 6: Verificar tipos e rodar a suíte do front**

Run: `npx tsc --noEmit -p tsconfig.json && npm run test`
Expected: sem erro de tipo; todos os testes de `resources/js/**` passando.

- [ ] **Step 7: Commitar**

```bash
git add resources/js/pages/auth/gestao-login-docking.ts resources/js/pages/auth/gestao-login-docking.test.ts resources/js/pages/auth/gestao-login.tsx
git commit -m "feat: login do console anima o alinhamento antes de abrir a central"
```

---

### Task 6: Entrada animada no console

**Files:**
- Create: `resources/js/components/app/console-entrance.ts`
- Test: `resources/js/components/app/console-entrance.test.ts`
- Modify: `resources/js/components/app/app-shell.tsx:34-35`
- Modify: `resources/css/app.css`

**Interfaces:**
- Consumes: nada.
- Produces (usado pela Task 5):
  - `marcarEntradaConsole(): void` — grava a marca em `sessionStorage`
  - `consumirEntradaConsole(): boolean` — lê e apaga a marca, devolvendo se existia
  - `CHAVE_ENTRADA_CONSOLE = 'viabiliza:entrada-console'`

**O que NÃO fazer:** não animar o logo da sidebar. O disco do login parou exatamente sobre ele, no mesmo tamanho e cor; qualquer fade ali cria a piscada que o critério de aceite 2 proíbe. E não animar os blocos de conteúdo: `PageTransition` já monta um `RevealScope` e o `dashboard.tsx` usa `Surface`/`RevealLink`, que entram em fila escalonada — uma segunda cascata animaria duas vezes.

- [ ] **Step 1: Escrever o teste falhando**

Criar `resources/js/components/app/console-entrance.test.ts`:

```ts
// @vitest-environment happy-dom
import { beforeEach, describe, expect, it } from 'vitest';
import { CHAVE_ENTRADA_CONSOLE, consumirEntradaConsole, marcarEntradaConsole } from './console-entrance';

describe('Marca de entrada no console', () => {
    beforeEach(() => sessionStorage.clear());

    it('sem marca, a entrada não é animada', () => {
        expect(consumirEntradaConsole()).toBe(false);
    });

    it('a marca gravada pelo login é consumida uma vez só', () => {
        marcarEntradaConsole();

        expect(sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE)).not.toBeNull();
        expect(consumirEntradaConsole()).toBe(true);
        expect(consumirEntradaConsole()).toBe(false);
        expect(sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE)).toBeNull();
    });
});
```

- [ ] **Step 2: Rodar o teste e confirmar que falha**

Run: `npx vitest run resources/js/components/app/console-entrance.test.ts`
Expected: FAIL. `Failed to resolve import "./console-entrance"`.

- [ ] **Step 3: Implementar a marca**

Criar `resources/js/components/app/console-entrance.ts`:

```ts
export const CHAVE_ENTRADA_CONSOLE = 'viabiliza:entrada-console';

/**
 * O login grava esta marca no último frame da cena de alinhamento, logo
 * antes de navegar. É o que diz ao console que esta chegada específica
 * continua uma animação — navegações seguintes entram sem cascata.
 */
export function marcarEntradaConsole(): void {
    try {
        sessionStorage.setItem(CHAVE_ENTRADA_CONSOLE, '1');
    } catch {
        // Armazenamento bloqueado: a entrada só fica sem animação.
    }
}

export function consumirEntradaConsole(): boolean {
    try {
        const marcado = sessionStorage.getItem(CHAVE_ENTRADA_CONSOLE) !== null;
        sessionStorage.removeItem(CHAVE_ENTRADA_CONSOLE);

        return marcado;
    } catch {
        return false;
    }
}
```

- [ ] **Step 4: Rodar o teste e confirmar que passa**

Run: `npx vitest run resources/js/components/app/console-entrance.test.ts`
Expected: PASS, 2 testes.

- [ ] **Step 5: Marcar o wrapper no `AppShell`**

Em `resources/js/components/app/app-shell.tsx`, acrescentar ao import do React:

```tsx
import { useState } from 'react';
import type { ReactNode } from 'react';
import { consumirEntradaConsole } from '@/components/app/console-entrance';
```

E dentro de `ShellContent`, antes do `return`:

```tsx
    // Consumido no primeiro render: a cascata roda só na chegada vinda do login.
    const [entradaDoLogin] = useState(consumirEntradaConsole);
```

Trocar a linha 35:

```tsx
        <div className="min-h-screen" data-entrada-console={entradaDoLogin ? 'true' : undefined}>
```

- [ ] **Step 6: Escrever a cascata em CSS**

Em `resources/css/app.css`, no fim do arquivo:

```css
/*
 * Entrada no console vinda da cena de alinhamento do login. O logo da
 * sidebar não entra nesta cascata de propósito: o disco do login parou
 * exatamente sobre ele, no mesmo tamanho e cor, e animá-lo criaria a
 * piscada que a transição existe para evitar. Os blocos de conteúdo
 * também ficam de fora — quem escalona o conteúdo é o RevealScope.
 */
@keyframes sile-console-surge {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: none;
    }
}

@keyframes sile-console-header {
    from {
        transform: translateY(-100%);
    }
    to {
        transform: none;
    }
}

[data-entrada-console='true'] aside > div:not(:first-child) {
    animation: sile-console-surge 0.45s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}

[data-entrada-console='true'] aside nav > div > div:nth-child(1) {
    animation-delay: 0.12s;
}

[data-entrada-console='true'] aside nav > div > div:nth-child(2) {
    animation-delay: 0.22s;
}

[data-entrada-console='true'] aside nav > div > div:nth-child(3) {
    animation-delay: 0.32s;
}

[data-entrada-console='true'] aside nav > div > div {
    animation: sile-console-surge 0.45s cubic-bezier(0.2, 0.7, 0.2, 1) both;
}

[data-entrada-console='true'] header {
    animation: sile-console-header 0.7s cubic-bezier(0.2, 0.7, 0.2, 1) 0.1s both;
}

@media (prefers-reduced-motion: reduce) {
    [data-entrada-console='true'] aside > div:not(:first-child),
    [data-entrada-console='true'] aside nav > div > div,
    [data-entrada-console='true'] header {
        animation: none;
    }
}
```

- [ ] **Step 7: Verificar tipos e build**

Run: `npx tsc --noEmit -p tsconfig.json && npm run build`
Expected: sem erros; build concluído.

- [ ] **Step 8: Commitar**

```bash
git add resources/js/components/app/console-entrance.ts resources/js/components/app/console-entrance.test.ts resources/js/components/app/app-shell.tsx resources/css/app.css
git commit -m "feat: console anima a chegada vinda da cena de login"
```

---

### Task 7: Verificação de ponta a ponta

**Files:** nenhum arquivo novo; esta tarefa só produz evidência e, se necessário, correções.

- [ ] **Step 1: Suíte completa do frontend**

Run: `npm run test`
Expected: PASS em todos os arquivos `resources/js/**/*.test.{ts,tsx}`.

- [ ] **Step 2: Suíte completa do backend**

Run: `php artisan test --compact`
Expected: PASS. Nenhum teste de `tests/Feature/Auth/` pode ter regredido.

- [ ] **Step 3: Build de produção**

Run: `npm run build`
Expected: build concluído sem aviso de chunk quebrado. `public/build` atualizado.

- [ ] **Step 4: Verificar os oito critérios de aceite no navegador**

Subir a aplicação (`composer run dev`) e percorrer, numa janela com pelo menos 1101px de largura:

1. Carregar `/gestao/login`: três anéis espalhados e desfocados, linha "01 / 06 · CENTRAL EM ESPERA".
2. Focar o identificador: anéis se aproximam, linha vai para "02 / 06 · OPERADOR INICIOU UMA SESSÃO", linha de conexão aparece apontando para o campo.
3. Digitar um e-mail `@salvador.ba.gov.br` e esperar meio segundo: o selo "AMBIENTE INTERNO" expande com "· E-mail institucional" e aparece "Operador identificado".
4. Digitar a senha: arco de autorização vai a 58 e depois a 86 quando os dois campos estão preenchidos.
5. Enviar com senha errada: arco fica vermelho em 16, formulário sacode, botão diz "Credencial não confere" e volta a `idle` com a caixa de erro.
6. Enviar com credencial válida: anéis vão ao centro, arco fecha girando, disco abre, fórmula aparece, palco contrai para 290px, disco pousa no logo e o console abre. **Observar o quadro da troca de página**: não pode haver piscada nem salto do logo.
7. Tirar o foco no meio de uma transição: o movimento é absorvido, sem salto.
8. Ligar "Reduzir movimento" no sistema e repetir: sem deriva, sem blur, sem voo; login entra direto.
9. Estreitar a janela abaixo de 1101px: só o formulário, sem cena.
10. Desligar o JavaScript e enviar o formulário: redireciona para o console normalmente.

- [ ] **Step 5: Confirmar 60 fps**

Com o DevTools em Performance, gravar do foco do campo até a abertura do console. Expected: sem frames longos acima de 16,7 ms de forma sustentada; nenhum `Recalculate Style` em cascata por frame.

- [ ] **Step 6: Commitar o build**

```bash
git add public/build
git commit -m "chore: atualiza o build com a cena de alinhamento do login"
```

---

## Notas para quem executar

- **Ordem obrigatória:** Task 6 antes da Task 5 (a orquestração importa `marcarEntradaConsole`). As Tasks 1, 2, 3 e 4 são independentes entre si.
- **Onde consultar quando o plano for ambíguo:** `prototipo/Login Gestao v3.dc.html`, método `targets()` e `frame()` da classe `Component`. Os screenshots `01`…`10` mostram um quadro por estado.
- **Não copiar do protótipo:** textos do palco, número da lei e a maquete do console. Tudo isso já existe de verdade no repositório.
