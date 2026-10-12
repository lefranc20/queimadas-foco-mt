<?php

namespace Tests\Feature\Api;

use App\Models\Foco;
use App\Models\Municipio;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipiosApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_lista_os_municipios_de_mt_em_ordem_alfabetica(): void
    {
        $this->seed(MunicipioSeeder::class);

        $resposta = $this->getJson('/api/municipios')->assertOk()->assertJsonCount(142);

        $this->assertSame(['id' => 5100102, 'nome' => 'Acorizal'], $resposta->json('0'));
    }

    public function test_detalha_o_municipio(): void
    {
        $sorriso = Municipio::factory()->create(['nome' => 'Sorriso']);
        $outro = Municipio::factory()->create();

        Foco::factory()->count(2)->em('2026-09-10')->for($sorriso)->create([
            'bioma' => 'Cerrado', 'numero_dias_sem_chuva' => 20, 'risco_fogo' => 0.8, 'frp' => 10,
        ]);
        Foco::factory()->em('2026-08-10')->for($sorriso)->create([
            'bioma' => 'Amazônia', 'numero_dias_sem_chuva' => 30, 'risco_fogo' => 0.6, 'frp' => 40,
        ]);
        Foco::factory()->count(2)->em('2025-08-05')->for($sorriso)->create(['bioma' => 'Cerrado']);
        Foco::factory()->count(5)->em('2026-09-10')->for($outro)->create();

        $resposta = $this->getJson("/api/municipios/{$sorriso->id}?inicio=2026-08-01&fim=2026-09-30");

        $resposta->assertOk()->assertJson([
            'id' => $sorriso->id,
            'nome' => 'Sorriso',
            'periodo' => ['inicio' => '2026-08-01', 'fim' => '2026-09-30'],
            'total' => 3,
            'posicao_ranking' => 2,
            'municipios_com_focos' => 2,
            'medias' => ['dias_sem_chuva' => 23.3, 'risco_fogo' => 0.73, 'frp' => 20],
            'biomas' => [['bioma' => 'Cerrado', 'total' => 2], ['bioma' => 'Amazônia', 'total' => 1]],
            'por_ano' => [['ano' => 2025, 'total' => 2], ['ano' => 2026, 'total' => 3]],
        ]);

        $serie = collect($resposta->json('serie_mensal'))->keyBy('periodo');
        $this->assertCount(9, $serie); // janeiro a setembro de 2026
        $this->assertSame(1, $serie['2026-08']['total']);
        $this->assertSame(2, $serie['2026-08']['total_ano_anterior']);
        $this->assertSame(2, $serie['2026-09']['total']);
    }

    public function test_municipio_sem_focos_fica_fora_do_ranking(): void
    {
        $municipio = Municipio::factory()->create();

        $this->getJson("/api/municipios/{$municipio->id}")
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('posicao_ranking', null);
    }

    public function test_municipio_inexistente_retorna_404(): void
    {
        $this->getJson('/api/municipios/9999999')->assertNotFound();
    }
}
