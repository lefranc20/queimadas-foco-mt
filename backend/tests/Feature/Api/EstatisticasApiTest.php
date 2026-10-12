<?php

namespace Tests\Feature\Api;

use App\Models\Foco;
use App\Models\Importacao;
use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class EstatisticasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_ranking_ordena_municipios_por_total_de_focos(): void
    {
        $colniza = Municipio::factory()->create(['nome' => 'Colniza']);
        $paranatinga = Municipio::factory()->create(['nome' => 'Paranatinga']);
        $cuiaba = Municipio::factory()->create(['nome' => 'Cuiabá']);

        Foco::factory()->count(3)->em('2026-09-10')->for($colniza)->create();
        Foco::factory()->count(1)->em('2026-09-10')->for($paranatinga)->create();
        Foco::factory()->count(5)->em('2025-09-10')->for($cuiaba)->create(); // fora do período

        $this->getJson('/api/municipios/ranking?inicio=2026-09-01&fim=2026-09-30')
            ->assertOk()
            ->assertExactJson([
                'periodo' => ['inicio' => '2026-09-01', 'fim' => '2026-09-30'],
                'total' => 4,
                'municipios' => [
                    ['id' => $colniza->id, 'nome' => 'Colniza', 'total' => 3, 'percentual' => 75],
                    ['id' => $paranatinga->id, 'nome' => 'Paranatinga', 'total' => 1, 'percentual' => 25],
                ],
            ]);
    }

    public function test_ranking_respeita_o_limite(): void
    {
        Foco::factory()->count(4)->em('2026-09-10')->create();

        $this->getJson('/api/municipios/ranking?inicio=2026-09-01&fim=2026-09-30&limite=2')
            ->assertOk()
            ->assertJsonCount(2, 'municipios');
    }

    public function test_serie_por_dia_preenche_dias_sem_focos_e_compara_com_ano_anterior(): void
    {
        Foco::factory()->count(2)->em('2026-09-01')->create(['numero_dias_sem_chuva' => 10, 'risco_fogo' => 0.5]);
        Foco::factory()->em('2026-09-03')->create(['numero_dias_sem_chuva' => null, 'risco_fogo' => null]);
        Foco::factory()->count(4)->em('2025-09-02')->create();

        $resposta = $this->getJson('/api/serie-temporal?inicio=2026-09-01&fim=2026-09-03');

        $resposta->assertOk()->assertJsonPath('agrupar', 'dia');
        $this->assertSame([
            ['periodo' => '2026-09-01', 'total' => 2, 'total_ano_anterior' => 0, 'media_dias_sem_chuva' => 10, 'media_risco_fogo' => 0.5],
            ['periodo' => '2026-09-02', 'total' => 0, 'total_ano_anterior' => 4, 'media_dias_sem_chuva' => null, 'media_risco_fogo' => null],
            ['periodo' => '2026-09-03', 'total' => 1, 'total_ano_anterior' => 0, 'media_dias_sem_chuva' => null, 'media_risco_fogo' => null],
        ], $resposta->json('serie'));
    }

    public function test_serie_agrupa_por_mes(): void
    {
        Foco::factory()->count(2)->em('2026-08-15')->create();
        Foco::factory()->count(3)->em('2026-09-20')->create();
        Foco::factory()->em('2025-09-01')->create();

        $resposta = $this->getJson('/api/serie-temporal?inicio=2026-08-01&fim=2026-09-30&agrupar=mes');

        $resposta->assertOk();
        $this->assertSame(
            [['2026-08', 2, 0], ['2026-09', 3, 1]],
            array_map(fn ($p) => [$p['periodo'], $p['total'], $p['total_ano_anterior']], $resposta->json('serie')),
        );
    }

    public function test_serie_longa_e_agrupada_por_mes_por_padrao(): void
    {
        $this->getJson('/api/serie-temporal?inicio=2026-01-01&fim=2026-12-31')
            ->assertOk()
            ->assertJsonPath('agrupar', 'mes')
            ->assertJsonCount(12, 'serie');

        $this->getJson('/api/serie-temporal?agrupar=semana')->assertJsonValidationErrors('agrupar');
    }

    public function test_resumo_compara_com_o_mesmo_periodo_do_ano_anterior(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 20:00:00'));

        Foco::factory()->count(2)->em('2026-09-15')->create();
        Foco::factory()->count(3)->em('2026-09-01')->create();
        Foco::factory()->count(5)->em('2026-01-10')->create();
        Foco::factory()->count(1)->em('2025-09-15')->create();
        Foco::factory()->count(4)->em('2025-09-16')->create(); // depois do "mesmo dia" do ano anterior
        Importacao::create(['tipo' => 'diario', 'arquivo' => 'x.csv', 'status' => Importacao::CONCLUIDA, 'concluida_em' => now()]);

        $this->getJson('/api/resumo')
            ->assertOk()
            ->assertJson([
                'data_referencia' => '2026-09-15',
                'hoje' => ['total' => 2, 'ano_anterior' => 1, 'variacao' => 100],
                'mes' => ['inicio' => '2026-09-01', 'total' => 5, 'ano_anterior' => 1, 'variacao' => 400],
                'ano' => ['inicio' => '2026-01-01', 'total' => 10, 'ano_anterior' => 1, 'variacao' => 900],
                'ultima_atualizacao' => '2026-09-15T20:00:00+00:00',
            ]);
    }

    public function test_resumo_sem_base_de_comparacao_tem_variacao_nula(): void
    {
        $this->travelTo(Carbon::parse('2026-09-15 20:00:00'));
        Foco::factory()->em('2026-09-15')->create(['satelite' => 'AQUA_M-T']);
        Foco::factory()->em('2025-09-15')->create(['satelite' => 'GOES-19']);

        $this->getJson('/api/resumo?satelite=AQUA_M-T')
            ->assertOk()
            ->assertJsonPath('hoje.total', 1)
            ->assertJsonPath('hoje.ano_anterior', 0)
            ->assertJsonPath('hoje.variacao', null);
    }

    public function test_metadados_lista_opcoes_dos_filtros(): void
    {
        Foco::factory()->em('2025-01-05')->create(['satelite' => 'GOES-19', 'bioma' => 'Pantanal']);
        Foco::factory()->em('2026-09-15')->create(['satelite' => 'AQUA_M-T', 'bioma' => 'Cerrado']);

        $this->getJson('/api/metadados')
            ->assertOk()
            ->assertExactJson([
                'satelite_referencia' => 'AQUA_M-T',
                'satelites' => ['AQUA_M-T', 'GOES-19'],
                'biomas' => ['Cerrado', 'Pantanal'],
                'periodo_disponivel' => ['inicio' => '2025-01-05', 'fim' => '2026-09-15'],
                'ultima_atualizacao' => null,
            ]);
    }
}
