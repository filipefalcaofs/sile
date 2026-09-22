# Fase 0 — Feature flag no bypass `simulacao_protocolo` — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** colocar o bypass de homologação que converte veredito `pendente` em `permitido` (com emissão de TVL) atrás da feature flag `features.simulacao_protocolo` — default **desligada** — sem quebrar o caminho de homologação quando ligada explicitamente.

**Architecture:** hoje `FluxoExpressoService::eSimulacaoRiscoRegin()` (linhas 285-289) identifica processos nascidos do simulador de protocolos SEDUR (`origin === Regin` + `contingency_reason === 'simulacao_protocolo'`) e dois ramos o usam: pendente && NÃO-simulação → análise (linha 222); pendente && simulação → **emite TVL como permitido** (linha 248). O gate entra dentro do predicado, renomeado para dizer o que faz: com a flag off, processo de simulação se comporta EXATAMENTE como processo normal (pendente → análise, sem exceção).

**Tech Stack:** Laravel 13, PHP 8.5, PHPUnit 12.

**Origem:** Fase 0 do backlog `docs/superpowers/plans/2026-09-17-parametrizacao-matrizes-auditoria.md` (auditoria 2026-09-17, achado CRÍTICO #7 — "não parametrizar, remover/togglar").

## Global Constraints

- TDD estrito: teste falhando antes, RED confirmado pelo motivo certo.
- `vendor/bin/pint --format agent <arquivos tocados>` — NUNCA `--dirty` (a árvore pode ter WIP de outra sessão).
- `git add` SOMENTE dos arquivos nomeados; nunca `git add -A`/`.`.
- Mensagens de commit em pt-BR, conventional commits, sem ponto final.
- Não remover o bypass — ele é o caminho de homologação do motor de risco com a SEDUR; a flag o desliga por default e a remoção definitiva fica registrada como pendência de go-live.
- A string `'simulacao_protocolo'` já existe como `ReginProtocoloSimulacaoService::CONTINGENCIA` — usar a constante, eliminando a magic string.

---

### Task 1: Flag `features.simulacao_protocolo` + gate no FluxoExpressoService

**Files:**
- Modify: `database/seeders/ParameterSeeder.php` (bloco `features.*`, após `features.fluxo_expresso` ~linha 374)
- Modify: `config/sile.php` (bloco `features`, ~linha 40)
- Modify: `app/Services/Expresso/FluxoExpressoService.php` (linhas 218-261 e 285-289)
- Modify: `tests/Feature/Risco/ReginProtocoloSimulacaoTest.php` (o teste do bypass passa a ligar a flag explicitamente)
- Modify: `tests/Feature/Seeders/ParameterSeederTest.php` e `tests/Feature/Seeders/DatabaseSeederTest.php` (contagem de parâmetros +1 — verificar o valor commitado atual antes de editar; era 116 em 3aaae26)
- Modify: `.planning/STATE.md` (registro da decisão + pendência de remoção antes do go-live)
- Test: `tests/Feature/Expresso/SimulacaoProtocoloFlagTest.php` (novo)

**Interfaces:**
- Consumes: `Settings::enabled(string $feature): bool` (lê `features.{name}`, banco → cache → `config/sile.php` → default false); `ReginProtocoloSimulacaoService::CONTINGENCIA`.
- Produces: parâmetro `features.simulacao_protocolo` (boolean, default '0'); predicado renomeado `FluxoExpressoService::bypassSimulacaoHomologacao(ViabilityRequest): bool`.

- [ ] **Step 1: Escrever o teste que falha**

```bash
php artisan make:test Expresso/SimulacaoProtocoloFlagTest --no-interaction
```

Espelhar o setup de `tests/Feature/Risco/ReginProtocoloSimulacaoTest.php::test_simulacao_baixo_cria_processo_expresso_com_tvl` (linha 222) — ler esse teste e reusar o caminho real de simulação que ele usa (serviço/comando que cria o processo de simulação com risco baixo). Casos:

```php
    public function test_flag_desligada_por_padrao_simulacao_pendente_vai_para_analise_sem_tvl(): void
    {
        // SEM config() da flag — o default commitado é off.
        // Rodar a mesma simulação de risco baixo do teste irmão.
        // Assert: processo NÃO tem decision, NÃO tem tvl_product_number,
        // status encaminhado à análise (espelhar os asserts do irmão no
        // caminho alto/sem-tvl: test_simulacao_alto_encaminha_para_analise_sem_tvl).
    }

    public function test_flag_ligada_preserva_o_caminho_de_homologacao_com_tvl(): void
    {
        config(['sile.features.simulacao_protocolo' => true]);

        // Mesma simulação de risco baixo.
        // Assert: TVL emitido (espelhar test_simulacao_baixo_cria_processo_expresso_com_tvl).
    }
```

- [ ] **Step 2: Rodar e confirmar o RED**

Run: `php artisan test --compact tests/Feature/Expresso/SimulacaoProtocoloFlagTest.php`
Expected: o PRIMEIRO teste FALHA — hoje a simulação pendente/baixa emite TVL mesmo sem flag (é o bypass). O segundo já passa (comportamento atual).

- [ ] **Step 3: Implementar o gate**

`config/sile.php`, bloco `features`, após `'fluxo_expresso' => true,`:

```php
        // Bypass de HOMOLOGAÇÃO do motor de risco (simulação REGIN): ligado,
        // processo nascido do simulador com veredito pendente (zona oficial
        // pendente SEDUR) emite TVL como permitido. DESLIGADO por default —
        // NUNCA ligar em produção; remoção definitiva antes do go-live.
        'simulacao_protocolo' => false,
```

`database/seeders/ParameterSeeder.php`, após a entrada `features.fluxo_expresso`:

```php
            'features.simulacao_protocolo' => [
                'group' => 'features',
                'type' => 'boolean',
                'default_value' => '0',
                'validation_rules' => ['required', 'boolean'],
                'description' => 'SOMENTE HOMOLOGAÇÃO: ligado, processos nascidos do simulador de protocolos REGIN com veredito pendente emitem TVL como permitidos (a zona oficial ainda não está na base). Desligado, seguem o fluxo normal (pendente → análise técnica). NUNCA ligar em produção; remoção prevista antes do go-live',
            ],
```

`FluxoExpressoService.php`: renomear `eSimulacaoRiscoRegin` para `bypassSimulacaoHomologacao`, trocar a magic string pela constante e adicionar a flag (imports: `App\Services\Regin\ReginProtocoloSimulacaoService`, `App\Support\Settings` — verificar os imports já existentes no arquivo):

```php
    /**
     * Bypass de homologação do motor de risco (simulação REGIN): só vale com
     * features.simulacao_protocolo LIGADA. Desligada (default), o processo de
     * simulação segue o fluxo normal — pendente vai à análise, sem exceção.
     */
    private function bypassSimulacaoHomologacao(ViabilityRequest $request): bool
    {
        return $request->origin === ViabilityRequestOrigin::Regin
            && $request->contingency_reason === ReginProtocoloSimulacaoService::CONTINGENCIA
            && Settings::enabled('simulacao_protocolo');
    }
```

Atualizar os dois call sites (linhas 223 e 249) para o novo nome e ajustar o comentário do bloco 218-221 ("Exceção honesta da homologação…") mencionando que a exceção exige a flag ligada.

- [ ] **Step 4: Rodar e confirmar o GREEN + caçar efeitos colaterais**

Run: `php artisan test --compact tests/Feature/Expresso/SimulacaoProtocoloFlagTest.php`
Expected: PASS (2 testes).

Run: `php artisan test --compact tests/Feature/Risco/ReginProtocoloSimulacaoTest.php`
Expected: `test_simulacao_baixo_cria_processo_expresso_com_tvl` FALHA (o bypass agora exige a flag). Corrigir o teste adicionando `config(['sile.features.simulacao_protocolo' => true]);` no início dele — o teste passa a documentar o caminho de homologação explicitamente. NUNCA afrouxar assert.

Atualizar as contagens de parâmetros (+1) em `ParameterSeederTest.php` e `DatabaseSeederTest.php`: ANTES de editar, ler o valor commitado em HEAD (`git show HEAD:tests/Feature/Seeders/ParameterSeederTest.php | grep -n "assertSame"`) — trabalho concorrente pode ter mudado; o incremento é sempre +1 sobre o valor commitado, e o comentário itemizado acima da contagem ganha uma linha para a nova flag.

Run: `php artisan test --compact` (suíte completa, ~4-5 min)
Expected: PASS exceto as 31 falhas pré-existentes conhecidas (30 testes PostGIS — banco espacial 127.0.0.1:5433 com auth failure — e `Tests\Feature\Analise\AnaliseSmokeTest::test_degradacao_sem_motor_o_humano_decide_em_modo_manual`). Qualquer outra falha é desta task.

- [ ] **Step 5: Registrar no STATE.md**

Adicionar ao `.planning/STATE.md` (lista de decisões, após a entrada da Fase 1 de tipos de imóvel):

```markdown
- [Parametrização — Fase 0, 2026-09-18] Bypass `simulacao_protocolo` (pendente→permitido com TVL para processos do simulador REGIN) agora atrás da flag `features.simulacao_protocolo`, DEFAULT OFF — em produção o processo de simulação segue o fluxo normal (pendente → análise). O caminho de homologação do motor de risco com a SEDUR continua disponível ligando a flag. PENDÊNCIA DE GO-LIVE: remover o bypass e a flag quando a zona oficial estiver na base e o REGIN real integrado (Fase 13).
```

Se o arquivo estiver sendo editado por outra sessão (conteúdo inesperado), não forçar — registrar a pendência no ledger `.superpowers/sdd/progress.md` e reportar como concern.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/ParameterSeeder.php config/sile.php app/Services/Expresso/FluxoExpressoService.php tests/Feature/Expresso/SimulacaoProtocoloFlagTest.php tests/Feature/Risco/ReginProtocoloSimulacaoTest.php tests/Feature/Seeders/ParameterSeederTest.php tests/Feature/Seeders/DatabaseSeederTest.php .planning/STATE.md
git commit -m "feat: coloca o bypass de simulação de protocolo atrás de feature flag"
```

---

## Self-Review

- **Cobertura da Fase 0 do backlog:** 0.1 flag no catálogo com descrição explícita → Step 3; 0.2 flag off = fluxo normal → Steps 1-3 (o gate dentro do predicado faz o ramo 222 capturar a simulação pendente); 0.3 teste de regressão sem TVL com flag off → Step 1; 0.4 registro de remoção → Step 5.
- **Risco verificado na escrita:** com a flag off, o ramo da linha 248 deixaria de capturar a simulação pendente e o processo cairia no `emitir()` final com consolidado `pendente` SE o predicado fosse aplicado só no ramo de emissão — por isso o gate entra DENTRO do predicado usado pelos dois ramos, e o teste do Step 1 prova o destino (análise, sem TVL).
- **Consistência:** nome `features.simulacao_protocolo` igual em config, seeder, service e testes; constante `CONTINGENCIA` referenciada, sem magic string.
