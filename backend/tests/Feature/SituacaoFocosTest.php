<?php

namespace Tests\Feature;

use App\Models\Foco;
use App\Models\FocoRecente;
use App\Models\Municipio;
use App\Services\IntensidadeFoco;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SituacaoFocosTest extends TestCase
{
    use RefreshDatabase;

    private int $sequencia = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));
    }

    public function test_fogo_repetido_no_mesmo_lugar_pede_atencao(): void
    {
        $municipio = Municipio::factory()->create(['nome' => 'Barão de Melgaço']);

        foreach (['09:00', '10:00', '11:00'] as $hora) {
            foreach ([[-16.0, -56.0], [-16.1, -56.1], [-16.2, -56.2], [-16.3, -56.3]] as [$lat, $lon]) {
                $this->foco($municipio, $lat, $lon, "2026-11-03 {$hora}:00", 'GOES-19');
            }
        }

        $this->getJson('/api/focos/recentes?horas=6')
            ->assertOk()
            ->assertJson(['municipios' => [[
                'nome' => 'Barão de Melgaço',
                'nivel' => 'atencao',
                'total' => 12,
                'satelites' => 1,
                'locais_repetidos' => 4,
                'duracao_horas' => 2,
                'ultima_deteccao' => '2026-11-03 11:00:00',
            ]]]);
    }

    public function test_muitos_focos_sem_repeticao_nao_viram_atencao(): void
    {
        $municipio = Municipio::factory()->create();

        for ($i = 0; $i < 12; $i++) {
            $this->foco($municipio, -16 - $i / 10, -56, '2026-11-03 10:00:00', 'NOAA-20');
        }

        $this->getJson('/api/focos/recentes?horas=6')->assertJsonPath('municipios.0.nivel', 'acompanhar');
    }

    public function test_ponto_unico_e_isolado_e_a_lista_vem_do_mais_grave_para_o_menos_grave(): void
    {
        $isolado = Municipio::factory()->create(['nome' => 'A Isolado']);
        $repetido = Municipio::factory()->create(['nome' => 'B Repetido']);

        $this->foco($isolado, -16, -56, '2026-11-03 10:00:00', 'NOAA-20');
        $this->foco($repetido, -12, -55, '2026-11-03 09:00:00', 'GOES-19');
        $this->foco($repetido, -12, -55, '2026-11-03 10:00:00', 'GOES-19');

        $this->getJson('/api/focos/recentes?horas=6')
            ->assertJsonPath('municipios.0.nome', 'B Repetido')
            ->assertJsonPath('municipios.0.nivel', 'acompanhar')
            ->assertJsonPath('municipios.1.nivel', 'isolado');
    }

    public function test_intensidade_e_relativa_ao_satelite(): void
    {
        $intensidade = app(IntensidadeFoco::class);

        $this->assertSame('fraca', $intensidade->classificar('GOES-19', 50));
        $this->assertSame('forte', $intensidade->classificar('NOAA-20', 50));
        $this->assertSame('muito forte', $intensidade->classificar('NOAA-20', 500));
        $this->assertNull($intensidade->classificar('SATELITE-NOVO', 50));
        $this->assertNull($intensidade->classificar('GOES-19', null));
    }

    public function test_focos_trazem_a_intensidade(): void
    {
        Foco::factory()->em('2026-11-02')->create(['satelite' => 'NOAA-20', 'frp' => 3.6]);

        $this->getJson('/api/focos?inicio=2026-11-01&fim=2026-11-03')->assertJsonPath('focos.0.intensidade', 'fraca');
    }

    public function test_pagina_do_municipio_resume_situacao_ultimo_foco_e_intensidade(): void
    {
        $municipio = Municipio::factory()->create();
        Foco::factory()->em('2026-11-01')->for($municipio)->create(['satelite' => 'GOES-19', 'frp' => 100]);
        Foco::factory()->em('2026-10-30')->for($municipio)->create(['satelite' => 'NOAA-20', 'frp' => 130]);
        Foco::factory()->em('2026-09-01')->for($municipio)->create(['satelite' => 'NOAA-20', 'frp' => 900]); // fora dos 7 dias
        $this->foco($municipio, -16, -56, '2026-11-03 10:00:00', 'NOAA-20');

        $this->getJson("/api/municipios/{$municipio->id}")
            ->assertOk()
            ->assertJsonPath('situacao.nivel', 'isolado')
            ->assertJsonPath('situacao.ultimo_foco', '2026-11-03 10:00:00')
            ->assertJsonPath('situacao.focos_7_dias', 2)
            ->assertJsonPath('situacao.dias_com_foco', 2)
            ->assertJsonPath('situacao.intensidade.nivel', 'muito forte')
            ->assertJsonPath('situacao.intensidade.satelite', 'NOAA-20');
    }

    public function test_municipio_sem_focos_recentes(): void
    {
        $municipio = Municipio::factory()->create();

        $this->getJson("/api/municipios/{$municipio->id}")
            ->assertJsonPath('situacao.nivel', 'sem_deteccao')
            ->assertJsonPath('situacao.ultimo_foco', null)
            ->assertJsonPath('situacao.intensidade', null);
    }

    private function foco(Municipio $municipio, float $lat, float $lon, string $dataHora, string $satelite): void
    {
        FocoRecente::create([
            'chave' => md5((string) ++$this->sequencia),
            'lat' => $lat,
            'lon' => $lon,
            'data_hora_gmt' => $dataHora,
            'satelite' => $satelite,
            'municipio_id' => $municipio->id,
        ]);
    }
}
