<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            LegalTermSeeder::class,
            ParameterSeeder::class,
            CnaeSeeder::class,
            RiscoMunicipalSeeder::class,
            RiscoSanitarioSeeder::class,
            RiskTriggerSeeder::class,
            LouosQuadro7Seeder::class,
            LouosQuadro10Seeder::class,
            LouosQuadro11Seeder::class,
            ViabilityServiceTypeSeeder::class,
            DocumentRequirementSeeder::class,
            DevAdminSeeder::class,
            CompanySeeder::class,
            GeoLayerSeeder::class,
            // Depende de empresas/CNAEs/catálogos acima — fecha o seed de dev.
            SolicitacaoDevSeeder::class,
            // Fluxo expresso (EP09) — SÓ dev/teste (gate de ambiente nos próprios
            // seeders). A zona fictícia (Centro) torna um deferimento navegável
            // sobre a LÓGICA REAL; os exemplos protocolam e decidem de verdade.
            // Em produção ambos são no-op (degradação honesta até a zona oficial).
            ZonaFicticiaDevSeeder::class,
            ExpressoDevSeeder::class,
            // Análise técnica SEDUR (EP10). Catálogos (setor + textos-padrão)
            // rodam sempre; o setor inicial evita distribuição bloqueada. Os
            // usuários de gestão dev (SectorSeeder) e os processos de exemplo em
            // cada estágio (AnaliseDevSeeder) são SÓ dev/teste, com LÓGICA REAL:
            // o AnaliseDevSeeder dirige distribuição/ficha/decisão/pendência/
            // malha fina pelos serviços reais — driver-aware (a decisão reexecuta
            // os motores territoriais, então só monta os estágios em pgsql; em
            // SQLite degrada honesto, igual ao ExpressoDevSeeder).
            SectorSeeder::class,
            StandardTextSeeder::class,
            AnaliseDevSeeder::class,
            // Comunicação multicanal (EP11) — fechamento. SÓ dev/teste, com LÓGICA
            // REAL e territorial-agnóstica: cria um processo dedicado, leva-o a
            // em_analise pelo caminho legítimo e roda o ciclo de pendência de
            // verdade (abrir → notifica o requerente in-app/e-mail; responder →
            // notifica o analista), deixando a central de notificações e o
            // histórico (HU-096) navegáveis. Roda também em SQLite (não exige
            // PostGIS). Idempotente por marcador estável.
            ComunicacaoDevSeeder::class,
            // Auditoria e compliance (EP12) — fechamento. SÓ dev/teste, com LÓGICA
            // REAL e territorial-agnóstica: semeia um padrão de abuso DEDICADO e
            // roda a detecção real (habilita o toggle só no seed → abuse_alerts +
            // 1 malha fina por sistema; restaura OFF), registra um acesso a dado
            // pessoal pelo AuditService real (painel LGPD) e leva um processo à
            // decisão pela análise técnica (decision_trace) além de uma decisão
            // legada sem trace — deixando trilha/explicabilidade/LGPD/abuso
            // navegáveis. Roda em SQLite e pgsql. Idempotente por marcadores
            // próprios (não perturba os exemplos do cidadão das fases anteriores).
            AuditoriaDevSeeder::class,
            // Relatórios e indicadores (EP15) — SÓ dev (gate `local`, fora do
            // testing): massa em estados variados (decisões/transições/quedas)
            // pelas factories do fluxo real, para o dashboard e as telas
            // calcularem sobre dado REAL (nunca número cravado). Idempotente por
            // requerente dedicado; em produção é no-op (degradação honesta).
            RelatoriosDevSeeder::class,
        ]);
    }
}
