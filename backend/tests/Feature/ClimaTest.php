<?php

namespace Tests\Feature;

use App\Models\ClimaDiario;
use App\Services\LocalizadorMunicipio;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClimaTest extends TestCase
{
    use RefreshDatabase;

    private const CUIABA = 5103403;

    private const SINOP = 5107909;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MunicipioSeeder::class);
        Http::preventStrayRequests();
    }

    public function test_importa_dias_encerrados_de_cada_municipio(): void
    {
        $this->travelTo(Carbon::parse('2026-11-03 12:00:00', 'UTC'));

        Http::fake(function ($request) {
            $locais = count(explode(',', urldecode($request->data()['latitude'])));

            return Http::response(array_fill(0, $locais, ['daily' => [
                'time' => ['2026-11-02', '2026-11-03'],
                'temperature_2m_mean' => [30.1, 31],
                'temperature_2m_max' => [36.5, 37],
                'temperature_2m_min' => [24.2, 25],
                'relative_humidity_2m_mean' => [57, 50],
                'precipitation_sum' => [1.5, 0],
            ]]));
        });

        $this->artisan('clima:importar', ['--dias' => 1])->assertSuccessful();

        $this->assertSame(0, ClimaDiario::where('data', '2026-11-03')->count()); // hoje ainda é previsão
        $this->assertGreaterThan(100, ClimaDiario::count());

        $dia = ClimaDiario::where('municipio_id', self::CUIABA)->sole();
        $this->assertEquals(36.5, $dia->temperatura_max);
        $this->assertEquals(1.5, $dia->chuva_mm);
    }

    public function test_consulta_o_clima_na_sede_e_nao_no_centro_do_territorio(): void
    {
        $sedes = app(LocalizadorMunicipio::class)->sedes();

        $this->assertSame(['lat' => -15.5958, 'lon' => -56.0969], $sedes[self::CUIABA]);
        $this->assertCount(142, $sedes);
    }

    public function test_rejeita_dias_invalidos(): void
    {
        $this->artisan('clima:importar', ['--dias' => 93])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_api_resume_o_periodo_por_municipio(): void
    {
        $this->clima(self::CUIABA, '2026-10-29', 30, 22, 38, 60, 8);
        $this->clima(self::CUIABA, '2026-10-30', 32, 24, 40, 40, 0.2);
        $this->clima(self::CUIABA, '2026-10-31', 34, 25, 41, 50, 0);

        $this->getJson('/api/clima?inicio=2026-10-30&fim=2026-10-31')
            ->assertOk()
            ->assertJsonCount(1, 'municipios')
            ->assertJson(['municipios' => [[
                'nome' => 'Cuiabá',
                'temperatura_media' => 33,
                'temperatura_min' => 24,
                'temperatura_max' => 41,
                'umidade_media' => 45,
                'chuva_mm' => 0.2,
                'dias_sem_chuva' => 2, // a última chuva de 1 mm ou mais foi em 29/10, fora do período
                'dias_sem_chuva_minimo' => false,
            ]]]);
    }

    public function test_informa_quando_o_clima_cobre_so_parte_do_periodo(): void
    {
        $this->clima(self::CUIABA, '2026-10-30', 30, 22, 38, 60, 0);
        $this->clima(self::CUIABA, '2026-10-31', 32, 24, 40, 40, 0);

        $this->getJson('/api/clima?inicio=2026-10-30&fim=2026-10-31')
            ->assertJsonPath('cobertura.parcial', false);

        $this->getJson('/api/clima?inicio=2025-11-04&fim=2026-11-03')
            ->assertJsonPath('cobertura', ['inicio' => '2026-10-30', 'fim' => '2026-10-31', 'dias' => 2, 'parcial' => true]);
    }

    public function test_sem_chuva_no_historico_marca_o_valor_como_minimo(): void
    {
        $this->clima(self::SINOP, '2026-10-30', 32, 24, 40, 40, 0);
        $this->clima(self::SINOP, '2026-10-31', 34, 25, 41, 50, 0);

        $this->getJson('/api/clima?inicio=2026-10-30&fim=2026-10-31')
            ->assertJsonPath('municipios.0.dias_sem_chuva', 1)
            ->assertJsonPath('municipios.0.dias_sem_chuva_minimo', true);
    }

    private function clima(int $municipio, string $data, float $media, float $min, float $max, float $umidade, float $chuva): void
    {
        ClimaDiario::query()->insert([
            'municipio_id' => $municipio, 'data' => $data, 'temperatura_media' => $media, 'temperatura_min' => $min,
            'temperatura_max' => $max, 'umidade_media' => $umidade, 'chuva_mm' => $chuva,
        ]);
    }
}
