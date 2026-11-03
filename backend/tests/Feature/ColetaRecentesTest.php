<?php

namespace Tests\Feature;

use App\Models\FocoRecente;
use App\Models\Importacao;
use App\Services\Inpe\ColetorFocosRecentes;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ColetaRecentesTest extends TestCase
{
    use RefreshDatabase;

    private const ARQUIVO = 'focos_10min_20261103_0040.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MunicipioSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-03 00:45:00', 'UTC'));
        Http::preventStrayRequests();
    }

    public function test_grava_so_focos_de_mt_com_o_municipio_pela_coordenada(): void
    {
        $this->fakeInpe();

        $this->artisan('focos:recentes', ['--horas' => 1])
            ->expectsOutputToContain('2 focos em MT')
            ->assertSuccessful();

        $this->assertSame(2, FocoRecente::count()); // fora: Pará e a linha repetida
        $this->assertSame(5103403, FocoRecente::where('satelite', 'GOES-19')->sole()->municipio_id); // Cuiabá
        $this->assertSame(5107909, FocoRecente::where('satelite', 'NOAA-20')->sole()->municipio_id); // Sinop
    }

    public function test_arquivo_ja_importado_nao_e_baixado_de_novo(): void
    {
        $this->fakeInpe();

        $this->artisan('focos:recentes', ['--horas' => 1])->assertSuccessful();
        $this->artisan('focos:recentes', ['--horas' => 1])->assertSuccessful();

        $downloads = Http::recorded(fn ($request) => str_ends_with($request->url(), self::ARQUIVO));

        $this->assertCount(1, $downloads);
        $this->assertSame(1, Importacao::where('arquivo', self::ARQUIVO)->count());
    }

    public function test_arquivo_ainda_nao_publicado_e_tentado_na_proxima_execucao(): void
    {
        Http::fake(['*' => Http::response('', 404)]);

        $this->artisan('focos:recentes', ['--horas' => 1])->assertSuccessful();

        $this->assertSame(0, Importacao::count());
    }

    public function test_apaga_focos_antigos(): void
    {
        $this->fakeInpe();
        FocoRecente::create([
            'chave' => str_repeat('a', 32), 'lat' => -15.6, 'lon' => -56.1,
            'data_hora_gmt' => '2026-10-30 00:00:00', 'satelite' => 'GOES-19', 'municipio_id' => 5103403,
        ]);

        $this->artisan('focos:recentes', ['--horas' => 1])->assertSuccessful();

        $this->assertNull(FocoRecente::find(str_repeat('a', 32)));
    }

    public function test_layout_desconhecido_falha_em_vez_de_importar_vazio(): void
    {
        Http::fake(['*'.self::ARQUIVO => Http::response("foo,bar\n1,2\n"), '*' => Http::response('', 404)]);

        $this->expectExceptionMessage('Layout inesperado');

        app(ColetorFocosRecentes::class)->coletar(1);
    }

    public function test_rejeita_horas_invalidas(): void
    {
        $this->artisan('focos:recentes', ['--horas' => 0])->assertExitCode(2);
        $this->artisan('focos:recentes', ['--horas' => 73])->assertExitCode(2);
    }

    public function test_api_devolve_focos_recentes_agrupados_por_municipio(): void
    {
        $this->fakeInpe();
        $this->artisan('focos:recentes', ['--horas' => 1])->assertSuccessful();

        $this->getJson('/api/focos/recentes?horas=1')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('ultima_deteccao', '2026-11-03 00:20:00')
            ->assertJsonPath('municipios.0.nome', 'Cuiabá')
            ->assertJsonPath('focos.0.frp', null);

        $this->getJson('/api/focos/recentes?horas=24')->assertOk()->assertJsonPath('total', 2);
        $this->getJson('/api/focos/recentes?horas=100')->assertUnprocessable();
    }

    private function fakeInpe(): void
    {
        $csv = file_get_contents(base_path('tests/fixtures/'.self::ARQUIVO));

        Http::fake([
            '*'.self::ARQUIVO => fn () => Http::response($csv),
            '*' => Http::response('', 404),
        ]);
    }
}
