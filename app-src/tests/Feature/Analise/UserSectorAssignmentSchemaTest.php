<?php

namespace Tests\Feature\Analise;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class UserSectorAssignmentSchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sectors_tem_codigo_cein_estavel(): void
    {
        $this->assertTrue(Schema::hasColumns('sectors', ['name', 'active', 'code']));
    }

    public function test_tabela_user_sector_assignments_existe_com_vigencia(): void
    {
        $this->assertTrue(Schema::hasTable('user_sector_assignments'));
        $this->assertTrue(Schema::hasColumns('user_sector_assignments', [
            'user_id',
            'sector_id',
            'previous_sector_id',
            'source',
            'started_at',
            'ended_at',
            'changed_by_user_id',
        ]));
    }
}
