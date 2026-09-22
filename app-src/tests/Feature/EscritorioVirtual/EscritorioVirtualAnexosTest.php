<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Enums\RuleDomain;
use App\Enums\RuleVersionStatus;
use App\Models\RuleVersion;
use App\Models\User;
use App\Models\VirtualOfficeActivityCnae;
use Database\Seeders\EscritorioVirtualCnaeSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Publicação versionada dos Anexos A/B do Decreto 35.062/2021 (escritório
 * virtual) pela retaguarda: o upload dos dois CSVs cai num RASCUNHO nomeado
 * (a vigente fica intacta até a publicação), o index expõe o diff
 * rascunho × vigente por anexo e a publicação exige quatro olhos — o domínio
 * atividades_escritorio_virtual é sensível (auditoria G7: publicar anexo
 * bloqueia/libera constituição de sede). Gate: manter-cnaes (reuso — os
 * anexos são listas de CNAE, mesmo mantenedor do risco).
 */
class EscritorioVirtualAnexosTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const VERSAO_RASCUNHO = 'ev-anexos-2099-01-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function administrador(): User
    {
        return User::factory()->administrador()->withAcceptedLgpdTerm()->create();
    }

    private function analista(): User
    {
        return User::factory()->analista()->withAcceptedLgpdTerm()->create();
    }

    /**
     * @param  list<array{0: string, 1: string}>  $linhas
     */
    private function csv(string $nome, array $linhas): UploadedFile
    {
        $conteudo = "cnae_code,cnae_description\n";

        foreach ($linhas as [$codigo, $descricao]) {
            $conteudo .= "{$codigo},{$descricao}\n";
        }

        return UploadedFile::fake()->createWithContent($nome, $conteudo);
    }

    /**
     * Upload dos dois anexos como o admin informado — o rascunho fica com
     * created_by desse autor (base do teste de quatro olhos).
     */
    private function uploadComoAutor(User $autor): void
    {
        $this->actingAs($autor, 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'Anexos A e B do Decreto 35.062/2021 (revisão de teste)',
                'anexo_a' => $this->csv('anexo-a.csv', [
                    ['8219-9/99', 'Preparação de documentos'],
                    ['1111-1/00', 'Atividade nova do Anexo A'],
                ]),
                'anexo_b' => $this->csv('anexo-b.csv', [
                    ['8219-9/99', 'Preparação de documentos'],
                ]),
            ])
            ->assertRedirect();
    }

    public function test_upload_cria_rascunho_sem_tocar_a_vigente(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $autor = $this->administrador();
        $this->uploadComoAutor($autor);

        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->sole();
        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->status);
        $this->assertSame($autor->id, $rascunho->created_by);

        // As linhas importadas caíram no RASCUNHO.
        $this->assertSame(2, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $rascunho->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
        $this->assertSame(1, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $rascunho->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());

        // A vigente oficial e seus counts NÃO mudaram.
        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole();
        $this->assertSame('ev-anexos-2026-08-28', $vigente->version);
        $this->assertSame(6, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
        $this->assertSame(319, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());

        // permitidoNoAnexo() continua respondendo pela VIGENTE.
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('1111-1/00', VirtualOfficeActivityCnae::ANEXO_A));
    }

    public function test_preview_mostra_diff_entre_rascunho_e_vigente(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->uploadComoAutor($this->administrador());

        $this->actingAs($this->administrador(), 'gestao')
            ->get('/gestao/escritorio-virtual/anexos')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('gestao/escritorio-virtual/anexos')
                ->where('vigente.version', 'ev-anexos-2026-08-28')
                ->where('rascunho.version', self::VERSAO_RASCUNHO)
                // Anexo A do rascunho: 8219-9/99 (já vigente) + 1111-1/00 (novo);
                // os outros 5 CNAEs da vigente saem.
                ->where('rascunho.diff.A.adicionados', ['1111100'])
                ->has('rascunho.diff.A.removidos', 5)
                // Anexo B do rascunho: só 8219-9/99, já vigente — nada entra,
                // os outros 318 saem.
                ->where('rascunho.diff.B.adicionados', [])
                ->has('rascunho.diff.B.removidos', 318)
            );
    }

    public function test_publicacao_exige_quatro_olhos(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $autor = $this->administrador();
        $this->uploadComoAutor($autor);

        // O autor do rascunho NÃO pode publicar — flash.error, nunca 500.
        $this->actingAs($autor, 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect()
            ->assertSessionHas('error', 'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.');

        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->sole();
        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->status);
        $this->assertSame('ev-anexos-2026-08-28', RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole()->version);

        // Outro admin publica: o rascunho vira vigente e a anterior é fechada.
        $revisor = $this->administrador();

        $this->actingAs($revisor, 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect();

        $publicada = $rascunho->fresh();
        $this->assertSame(RuleVersionStatus::Vigente, $publicada->status);
        $this->assertSame($revisor->id, $publicada->published_by);

        $anterior = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, 'ev-anexos-2026-08-28')->sole();
        $this->assertSame(RuleVersionStatus::Substituida, $anterior->status);
        $this->assertNotNull($anterior->valid_to);

        // permitidoNoAnexo() passa a responder pela NOVA lista.
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('1111-1/00', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('6920-6/01', VirtualOfficeActivityCnae::ANEXO_A));
    }

    /**
     * Reimportação no MESMO rascunho (idempotente pelo nome): a nova carga
     * substitui o conteúdo do rascunho — linhas ausentes dos CSVs novos são
     * removidas DO RASCUNHO (ele é descartável) — e a vigente fica intacta.
     */
    public function test_reimportacao_no_mesmo_rascunho_substitui_o_conteudo_sem_tocar_a_vigente(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $autor = $this->administrador();
        $this->uploadComoAutor($autor);

        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->sole();
        $this->assertSame(2, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $rascunho->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());

        // Reupload com o MESMO nome de versão e CSVs menores.
        $this->actingAs($autor, 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'Anexos A e B do Decreto 35.062/2021 (revisão menor)',
                'anexo_a' => $this->csv('anexo-a.csv', [
                    ['1111-1/00', 'Atividade nova do Anexo A'],
                ]),
                'anexo_b' => $this->csv('anexo-b.csv', [
                    ['2222-2/00', 'Atividade nova do Anexo B'],
                ]),
            ])
            ->assertRedirect();

        // O rascunho é o MESMO (idempotente) e o conteúdo foi substituído:
        // 8219-9/99 saiu do rascunho, ficaram só os CNAEs dos CSVs novos.
        $this->assertSame(1, RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->count());
        $this->assertSame(
            ['1111100'],
            VirtualOfficeActivityCnae::query()
                ->where('rule_version_id', $rascunho->id)
                ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
                ->pluck('cnae_code')
                ->all(),
        );
        $this->assertSame(
            ['2222200'],
            VirtualOfficeActivityCnae::query()
                ->where('rule_version_id', $rascunho->id)
                ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
                ->pluck('cnae_code')
                ->all(),
        );

        // A vigente oficial e o permitidoNoAnexo() NÃO mudaram.
        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole();
        $this->assertSame('ev-anexos-2026-08-28', $vigente->version);
        $this->assertSame(6, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
        $this->assertSame(319, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('8219-9/99', VirtualOfficeActivityCnae::ANEXO_A));
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('1111-1/00', VirtualOfficeActivityCnae::ANEXO_A));
    }

    /**
     * Guarda de histórico: um nome de versão JÁ PUBLICADO não pode ser
     * reescrito por um novo upload — o DomainException vira flash.error
     * (nunca 500) e a versão publicada fica intacta.
     */
    public function test_upload_com_nome_de_versao_ja_publicada_e_rejeitado(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $autor = $this->administrador();
        $this->uploadComoAutor($autor);

        // Outro admin publica o rascunho (quatro olhos).
        $this->actingAs($this->administrador(), 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect();

        // Novo upload com o MESMO nome de versão: rejeitado, sem 500.
        $this->actingAs($autor, 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'Tentativa de reescrita do histórico',
                'anexo_a' => $this->csv('anexo-a.csv', [['3333-3/00', 'Intruso A']]),
                'anexo_b' => $this->csv('anexo-b.csv', [['3333-3/00', 'Intruso B']]),
            ])
            ->assertRedirect()
            ->assertSessionHas(
                'error',
                "A versão '".self::VERSAO_RASCUNHO."' já foi publicada e não pode ser reescrita — informe outro nome de versão.",
            );

        // A versão publicada segue vigente e intacta (2 A + 1 B do upload
        // original) — o CNAE intruso não entrou em lugar nenhum.
        $vigente = RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole();
        $this->assertSame(self::VERSAO_RASCUNHO, $vigente->version);
        $this->assertSame(2, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_A)
            ->count());
        $this->assertSame(1, VirtualOfficeActivityCnae::query()
            ->where('rule_version_id', $vigente->id)
            ->where('anexo', VirtualOfficeActivityCnae::ANEXO_B)
            ->count());
        $this->assertFalse(VirtualOfficeActivityCnae::permitidoNoAnexo('3333-3/00', VirtualOfficeActivityCnae::ANEXO_A));

        // Histórico preservado: a oficial substituída + a publicada.
        $this->assertSame(2, RuleVersion::query()
            ->where('domain', RuleDomain::AtividadesEscritorioVirtual->value)
            ->count());
    }

    /**
     * CSV fora do cabeçalho esperado: o RuntimeException do import vira
     * flash.error com mensagem amigável — SEM o path absoluto do arquivo
     * temporário (que vai para o log) — e a transação rola back (nenhum
     * rascunho nem linha persistem).
     */
    public function test_csv_com_cabecalho_invalido_retorna_erro_amigavel_sem_path(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $this->actingAs($this->administrador(), 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'fonte',
                'anexo_a' => UploadedFile::fake()->createWithContent(
                    'anexo-a.csv',
                    "codigo,descricao\n1111-1/00,X\n",
                ),
                'anexo_b' => $this->csv('anexo-b.csv', [['1111-1/00', 'X']]),
            ])
            ->assertRedirect()
            ->assertSessionHas('error', 'Cabeçalho inesperado no CSV do Anexo A: esperado cnae_code,cnae_description.');

        // Rollback da transação: nem o rascunho nem linhas persistiram.
        $this->assertSame(0, RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->count());
        $this->assertSame(325, VirtualOfficeActivityCnae::query()->count()); // só a vigente oficial
    }

    /**
     * Publicar um nome de versão sem rascunho aberto: flash.error, nunca 500.
     */
    public function test_publicar_versao_sem_rascunho_retorna_flash_error(): void
    {
        $this->actingAs($this->administrador(), 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/ev-anexos-2000-01-01/publicar')
            ->assertRedirect()
            ->assertSessionHas('error', 'Nenhum rascunho aberto com esta versão para publicação.');
    }

    /**
     * Quatro olhos na REIMPORTAÇÃO: quem reimporta o rascunho (apagando e
     * reescrevendo o conteúdo) é o autor do conteúdo ATUAL — a autoria do
     * rascunho é transferida para ele. Sem a transferência, a garantia
     * inverteria: o reimportador publicaria o próprio conteúdo e o autor
     * original ficaria bloqueado sem ter escrito nada do que vigora.
     */
    public function test_reimportacao_por_outro_autor_transfere_a_autoria_do_rascunho(): void
    {
        $this->seed(EscritorioVirtualCnaeSeeder::class);

        $autorOriginal = $this->administrador();
        $this->uploadComoAutor($autorOriginal);

        // Outro mantenedor reimporta o MESMO rascunho com conteúdo novo.
        $reimportador = $this->administrador();

        $this->actingAs($reimportador, 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'Anexos A e B do Decreto 35.062/2021 (revisão do reimportador)',
                'anexo_a' => $this->csv('anexo-a.csv', [['1111-1/00', 'Atividade nova do Anexo A']]),
                'anexo_b' => $this->csv('anexo-b.csv', [['2222-2/00', 'Atividade nova do Anexo B']]),
            ])
            ->assertRedirect();

        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->sole();
        $this->assertSame($reimportador->id, $rascunho->created_by);
        $this->assertSame('Anexos A e B do Decreto 35.062/2021 (revisão do reimportador)', $rascunho->source);

        // O reimportador (autor do conteúdo atual) NÃO pode publicar.
        $this->actingAs($reimportador, 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect()
            ->assertSessionHas('error', 'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.');

        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->fresh()->status);

        // O autor original (não escreveu o conteúdo atual) PODE publicar.
        $this->actingAs($autorOriginal, 'gestao')
            ->put('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect();

        $publicada = $rascunho->fresh();
        $this->assertSame(RuleVersionStatus::Vigente, $publicada->status);
        $this->assertSame($autorOriginal->id, $publicada->published_by);
        $this->assertTrue(VirtualOfficeActivityCnae::permitidoNoAnexo('1111-1/00', VirtualOfficeActivityCnae::ANEXO_A));
    }

    public function test_dominio_escritorio_virtual_agora_e_sensivel(): void
    {
        $this->assertTrue(RuleDomain::AtividadesEscritorioVirtual->isSensitive());
    }

    public function test_acesso_exige_permissao_manter_cnaes(): void
    {
        // Analista acessa a gestão mas NÃO tem manter-cnaes.
        $this->actingAs($this->analista(), 'gestao')
            ->get('/gestao/escritorio-virtual/anexos')
            ->assertForbidden();

        $this->actingAs($this->analista(), 'gestao')
            ->post('/gestao/escritorio-virtual/anexos', [
                'versao' => self::VERSAO_RASCUNHO,
                'fonte' => 'fonte',
                'anexo_a' => $this->csv('anexo-a.csv', [['1111-1/00', 'X']]),
                'anexo_b' => $this->csv('anexo-b.csv', [['1111-1/00', 'X']]),
            ])
            ->assertForbidden();
    }
}
