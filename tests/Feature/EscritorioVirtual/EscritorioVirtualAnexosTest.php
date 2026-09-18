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
            ->post('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
            ->assertRedirect()
            ->assertSessionHas('error', 'A publicação por quatro olhos exige um publicador diferente do autor do rascunho.');

        $rascunho = RuleVersion::versao(RuleDomain::AtividadesEscritorioVirtual, self::VERSAO_RASCUNHO)->sole();
        $this->assertSame(RuleVersionStatus::Rascunho, $rascunho->status);
        $this->assertSame('ev-anexos-2026-08-28', RuleVersion::vigente(RuleDomain::AtividadesEscritorioVirtual)->sole()->version);

        // Outro admin publica: o rascunho vira vigente e a anterior é fechada.
        $revisor = $this->administrador();

        $this->actingAs($revisor, 'gestao')
            ->post('/gestao/escritorio-virtual/anexos/'.self::VERSAO_RASCUNHO.'/publicar')
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
