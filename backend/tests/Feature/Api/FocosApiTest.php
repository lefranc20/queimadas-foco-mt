<?php

namespace Tests\Feature\Api;

use App\Models\Foco;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class FocosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_focos_do_periodo_com_o_nome_do_municipio(): void
    {
        $cuiaba = Municipio::factory()->create(['id' => 5103403, 'nome' => 'Cuiabá']);
        Foco::factory()->em('2026-09-10')->for($cuiaba)->create([
            'id' => '0b6f7f3e-0000-3000-8000-000000000001',
            'lat' => -15.6,
            'lon' => -56.1,
            'satelite' => 'AQUA_M-T',
            'bioma' => 'Cerrado',
            'frp' => 12.5,
        ]);
        Foco::factory()->em('2026-08-01')->create();

        $this->getJson('/api/focos?inicio=2026-09-01&fim=2026-09-30')
            ->assertOk()
            ->assertJson([
                'periodo' => ['inicio' => '2026-09-01', 'fim' => '2026-09-30'],
                'total' => 1,
                'truncado' => false,
                'focos' => [[
                    'id' => '0b6f7f3e-0000-3000-8000-000000000001',
                    'lat' => -15.6,
                    'lon' => -56.1,
                    'data_hora_gmt' => '2026-09-10 17:30:00',
                    'satelite' => 'AQUA_M-T',
                    'municipio_id' => 5103403,
                    'municipio' => 'Cuiabá',
                    'bioma' => 'Cerrado',
                    'frp' => 12.5,
                ]],
            ]);
    }

    public function test_filtra_por_municipio_bioma_e_satelite(): void
    {
        $municipio = Municipio::factory()->create();
        $alvo = Foco::factory()->em('2026-09-10')->for($municipio)->create(['bioma' => 'Pantanal', 'satelite' => 'AQUA_M-T']);
        Foco::factory()->em('2026-09-10')->for($municipio)->create(['bioma' => 'Cerrado', 'satelite' => 'AQUA_M-T']);
        Foco::factory()->em('2026-09-10')->for($municipio)->create(['bioma' => 'Pantanal', 'satelite' => 'GOES-19']);
        Foco::factory()->em('2026-09-10')->create(['bioma' => 'Pantanal', 'satelite' => 'AQUA_M-T']);

        $resposta = $this->getJson("/api/focos?inicio=2026-09-01&fim=2026-09-30&municipio={$municipio->id}&bioma=Pantanal&satelite=AQUA_M-T");

        $resposta->assertOk()->assertJsonPath('total', 1)->assertJsonPath('focos.0.id', $alvo->id);
    }

    public function test_sem_periodo_usa_os_ultimos_30_dias(): void
    {
        $this->travelTo(Carbon::parse('2026-10-12 15:00:00'));
        Foco::factory()->em('2026-09-13')->create();
        Foco::factory()->em('2026-09-12')->create();

        $this->getJson('/api/focos')
            ->assertOk()
            ->assertJsonPath('periodo', ['inicio' => '2026-09-13', 'fim' => '2026-10-12'])
            ->assertJsonPath('total', 1);
    }

    public function test_respeita_o_limite_e_retorna_os_mais_recentes(): void
    {
        Foco::factory()->em('2026-09-01')->create();
        $recente = Foco::factory()->em('2026-09-03')->create();
        Foco::factory()->em('2026-09-02')->create();

        $this->getJson('/api/focos?inicio=2026-09-01&fim=2026-09-30&limite=1')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('truncado', true)
            ->assertJsonCount(1, 'focos')
            ->assertJsonPath('focos.0.id', $recente->id);
    }

    public function test_valida_os_parametros(): void
    {
        $this->getJson('/api/focos?inicio=2026-09-30&fim=2026-09-01')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['fim' => 'igual ou posterior']);

        $this->getJson('/api/focos?inicio=30/09/2026&limite=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['inicio', 'limite']);
    }
}
