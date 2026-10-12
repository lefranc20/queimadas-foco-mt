<?php

namespace Tests\Feature;

use App\Jobs\ImportarFocosHistoricos;
use App\Models\Foco;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportarFocosHistoricoTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://dataserver-coids.inpe.br/queimadas/queimadas/focos/csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MunicipioSeeder::class);
        Http::preventStrayRequests();
    }

    public function test_importa_o_arquivo_anual_do_satelite_de_referencia(): void
    {
        Http::fake([
            self::BASE.'/anual/EstadosBr_sat_ref/MT/focos_br_mt_ref_2024.zip' => Http::response($this->fixture('focos_br_mt_ref_2024.zip')),
        ]);

        $this->artisan('focos:importar-historico', ['ano' => 2024])->assertSuccessful();

        $this->assertSame(3, Foco::count());
        $this->assertSame(3, Foco::where('satelite', 'AQUA_M-T')->count());

        $foco = Foco::findOrFail('1c0a0000-0000-3000-8000-000000000002');
        $this->assertSame(-17.49043, $foco->lat);
        $this->assertSame('2024-01-05', $foco->data->format('Y-m-d'));
        $this->assertSame('Itiquira', $foco->municipio->nome);
    }

    public function test_resolve_municipio_pela_grafia_do_inpe(): void
    {
        Http::fake([
            self::BASE.'/anual/EstadosBr_sat_ref/MT/*' => Http::response($this->fixture('focos_br_mt_ref_2024.zip')),
        ]);

        $this->artisan('focos:importar-historico', ['ano' => 2024])->assertSuccessful();

        // "SANTO ANTÔNIO DO LEVERGER" no INPE, "Santo Antônio de Leverger" no IBGE
        $this->assertSame(5107800, Foco::findOrFail('1c0a0000-0000-3000-8000-000000000003')->municipio_id);
    }

    public function test_usa_o_arquivo_do_brasil_quando_nao_ha_arquivo_de_mt(): void
    {
        Http::fake([
            self::BASE.'/anual/EstadosBr_sat_ref/*' => Http::response('', 404),
            self::BASE.'/anual/Brasil_sat_ref/focos_br_ref_2025.zip' => Http::response($this->fixture('focos_sem_compressao.zip')),
        ]);

        $this->artisan('focos:importar-historico', ['ano' => 2025])->assertSuccessful();

        $this->assertSame(3, Foco::count());
    }

    public function test_usa_os_arquivos_mensais_quando_nao_ha_arquivo_anual(): void
    {
        $this->travelTo(Carbon::parse('2026-09-20 12:00:00'));

        Http::fake([
            self::BASE.'/anual/*' => Http::response('', 404),
            self::BASE.'/mensal/Brasil/focos_mensal_br_202609.csv' => Http::response($this->fixture('focos_diario_br_20260915.csv')),
            self::BASE.'/mensal/*' => Http::response('', 404),
        ]);

        $this->artisan('focos:importar-historico', ['ano' => 2026])->assertSuccessful();

        $this->assertSame(3, Foco::count());
        Http::assertSentCount(2 + 9); // dois anuais + janeiro a setembro
    }

    public function test_arquivo_anual_nao_apaga_dados_da_importacao_diaria(): void
    {
        $foco = Foco::factory()->create([
            'id' => '1c0a0000-0000-3000-8000-000000000001',
            'satelite' => 'AQUA_M-T',
            'risco_fogo' => 0.9,
            'frp' => 45.5,
        ]);

        Http::fake([
            self::BASE.'/anual/EstadosBr_sat_ref/MT/*' => Http::response($this->fixture('focos_br_mt_ref_2024.zip')),
        ]);

        $this->artisan('focos:importar-historico', ['ano' => 2024])->assertSuccessful();

        $foco->refresh();
        $this->assertSame(3, Foco::count());
        $this->assertSame(0.9, $foco->risco_fogo);
        $this->assertSame(45.5, $foco->frp);
        $this->assertSame('2024-01-02', $foco->data->format('Y-m-d'));
    }

    public function test_falha_quando_nao_existe_nenhum_arquivo(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('focos:importar-historico', ['ano' => 2010])
            ->expectsOutputToContain('Nenhum arquivo')
            ->assertFailed();
    }

    public function test_rejeita_ano_fora_do_intervalo(): void
    {
        $this->artisan('focos:importar-historico', ['ano' => 1990])->assertExitCode(2);
        $this->artisan('focos:importar-historico', ['ano' => 'abc'])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_opcao_fila_despacha_o_job(): void
    {
        Queue::fake();

        $this->artisan('focos:importar-historico', ['ano' => 2024, '--fila' => true, '--todos-satelites' => true])
            ->assertSuccessful();

        Queue::assertPushed(ImportarFocosHistoricos::class, fn ($job) => $job->ano === 2024 && $job->todosSatelites);
    }

    private function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/fixtures/{$nome}"));
    }
}
