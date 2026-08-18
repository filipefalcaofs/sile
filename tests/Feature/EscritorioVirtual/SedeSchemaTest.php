<?php

namespace Tests\Feature\EscritorioVirtual;

use App\Models\ViabilityRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SedeSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_wants_virtual_office_hq_e_fillable_e_bool(): void
    {
        $request = ViabilityRequest::factory()->create(['wants_virtual_office_hq' => true]);
        $this->assertTrue($request->fresh()->wants_virtual_office_hq);
    }
}
