<?php

namespace Tests\Feature\Cidadao;

use App\Models\AssistedAttendance;
use App\Models\Cidadao;
use App\Models\Procuration;
use App\Models\User;
use App\Support\Representation\CurrentRepresentation;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RepresentacaoTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_procuracao_liga_dois_cidadaos(): void
    {
        $outorgante = Cidadao::factory()->create();
        $procurador = Cidadao::factory()->create();

        $procuracao = Procuration::factory()->create([
            'grantor_cidadao_id' => $outorgante->id,
            'attorney_cidadao_id' => $procurador->id,
        ]);

        $this->assertTrue($procuracao->grantor->is($outorgante));
        $this->assertTrue($procuracao->attorney->is($procurador));
    }

    public function test_procurador_assume_a_representacao_pelo_outorgante(): void
    {
        $outorgante = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procurador = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procuracao = Procuration::factory()->create([
            'grantor_cidadao_id' => $outorgante->id,
            'attorney_cidadao_id' => $procurador->id,
        ]);

        $this->actingAs($procurador)
            ->post('/portal/representacao', ['procuration_id' => $procuracao->id])
            ->assertRedirect('/portal/painel');

        $this->assertSame($procuracao->id, session('acting_procuration_id'));
        $this->assertSame($outorgante->id, Context::get('acting_for_cidadao_id'));
        $this->assertDatabaseHas('activity_log', [
            'event' => 'representacao-iniciada',
            'acting_for_cidadao_id' => $outorgante->id,
        ]);

        $this->actingAs($procurador)->get('/portal/painel')->assertOk();

        $grantor = app(CurrentRepresentation::class)->grantor();
        $this->assertInstanceOf(Cidadao::class, $grantor);
        $this->assertTrue($grantor->is($outorgante));
    }

    public function test_so_o_procurador_cidadao_assume_a_procuracao(): void
    {
        $outorgante = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procurador = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $terceiro = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procuracao = Procuration::factory()->create([
            'grantor_cidadao_id' => $outorgante->id,
            'attorney_cidadao_id' => $procurador->id,
        ]);

        $this->actingAs($terceiro)
            ->post('/portal/representacao', ['procuration_id' => $procuracao->id])
            ->assertSessionHasErrors('procuration_id');

        $this->assertNull(session('acting_procuration_id'));
    }

    public function test_revogar_grava_o_cidadao_que_revogou(): void
    {
        $outorgante = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procuracao = Procuration::factory()->create(['grantor_cidadao_id' => $outorgante->id]);

        $this->actingAs($outorgante)->delete("/portal/procuracoes/{$procuracao->id}")->assertRedirect();

        $procuracao->refresh();
        $this->assertSame($outorgante->id, $procuracao->revoked_by_cidadao_id);
        $this->assertTrue($procuracao->revokedBy->is($outorgante));
    }

    public function test_servidor_nao_pode_ser_procurador_por_email(): void
    {
        $outorgante = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $servidor = User::factory()->administrador()->create();

        $this->actingAs($outorgante)
            ->post('/portal/procuracoes', ['attorney_email' => $servidor->email])
            ->assertSessionHasErrors('attorney_email');

        $this->assertDatabaseCount('procurations', 0);
    }

    public function test_servidor_com_id_igual_ao_do_cidadao_nao_assume_nem_revoga_procuracao(): void
    {
        $servidor = User::factory()->administrador()->create();
        $outorgante = Cidadao::factory()->withAcceptedLgpdTerm()->create(['id' => $servidor->id]);
        $procurador = Cidadao::factory()->withAcceptedLgpdTerm()->create();
        $procuracao = Procuration::factory()->create([
            'grantor_cidadao_id' => $outorgante->id,
            'attorney_cidadao_id' => $procurador->id,
        ]);
        $this->assertSame($servidor->id, $outorgante->id);

        $this->actingAs($servidor, 'gestao')
            ->post('/portal/representacao', ['procuration_id' => $procuracao->id])
            ->assertRedirect(route('login'));
        $this->assertNull(session('acting_procuration_id'));

        $this->actingAs($servidor, 'gestao')
            ->delete("/portal/procuracoes/{$procuracao->id}")
            ->assertRedirect(route('login'));
        $this->assertNull($procuracao->refresh()->revoked_at);
    }

    public function test_atendimento_presencial_liga_um_cidadao(): void
    {
        $atendente = User::factory()->administrador()->create();
        $cidadao = Cidadao::factory()->create();

        $atendimento = AssistedAttendance::factory()->active()->create([
            'attendant_user_id' => $atendente->id,
            'citizen_cidadao_id' => $cidadao->id,
        ]);

        $this->assertInstanceOf(Cidadao::class, $atendimento->citizen);
        $this->assertTrue($atendimento->citizen->is($cidadao));
        $this->assertTrue($atendimento->attendant->is($atendente));

        $representacao = app(CurrentRepresentation::class);
        $representacao->setAttendance($atendimento);
        $this->assertInstanceOf(Cidadao::class, $representacao->grantor());
    }

    public function test_colunas_de_identidade_apontam_para_cidadaos(): void
    {
        foreach (['grantor_cidadao_id', 'attorney_cidadao_id', 'revoked_by_cidadao_id'] as $coluna) {
            $this->assertTrue(Schema::hasColumn('procurations', $coluna), $coluna);
        }
        $this->assertTrue(Schema::hasColumn('assisted_attendances', 'citizen_cidadao_id'));
        $this->assertTrue(Schema::hasColumn('activity_log', 'acting_for_cidadao_id'));
        $this->assertFalse(Schema::hasColumn('activity_log', 'acting_for_user_id'));

        $esperadas = [
            'procurations.grantor_cidadao_id' => 'cascade',
            'procurations.attorney_cidadao_id' => 'cascade',
            'procurations.revoked_by_cidadao_id' => 'set null',
            'assisted_attendances.citizen_cidadao_id' => 'cascade',
        ];
        $encontradas = [];
        foreach (['procurations', 'assisted_attendances', 'activity_log'] as $tabela) {
            foreach (Schema::getForeignKeys($tabela) as $fk) {
                $chave = "{$tabela}.{$fk['columns'][0]}";
                if (isset($esperadas[$chave])) {
                    $this->assertSame('cidadaos', $fk['foreign_table'], $chave);
                    $this->assertSame($esperadas[$chave], $fk['on_delete'], $chave);
                    $encontradas[] = $chave;
                } else {
                    $this->assertSame('assisted_attendances.attendant_user_id', $chave, "{$chave} inesperada");
                    $this->assertSame('users', $fk['foreign_table']);
                }
            }
        }
        $this->assertEqualsCanonicalizing(array_keys($esperadas), $encontradas);
    }
}
