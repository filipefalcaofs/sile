<?php

namespace Tests\Feature;

// use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        // A raiz redireciona para o portal público (fase 2.3).
        $this->get('/')->assertRedirect('/portal');

        $this->get('/portal')->assertStatus(200);
    }
}
