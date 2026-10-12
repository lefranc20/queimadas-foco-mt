<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RaizApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_raiz_lista_os_endpoints_da_api(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertJsonPath('endpoints.focos', url('/api/focos'));
    }

    public function test_rota_inexistente_da_api_responde_404_em_json(): void
    {
        $this->getJson('/api/inexistente')->assertNotFound()->assertJsonStructure(['message']);
    }

    public function test_api_libera_acesso_de_outra_origem(): void
    {
        $this->getJson('/api/municipios', ['Origin' => 'https://painel.exemplo.com'])
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
