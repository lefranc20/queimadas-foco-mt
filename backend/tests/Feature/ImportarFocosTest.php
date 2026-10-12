<?php

namespace Tests\Feature;

use App\Jobs\ImportarFocosDiarios;
use App\Models\Foco;
use App\Models\Importacao;
use App\Services\Inpe\ColetorFocos;
use Database\Seeders\MunicipioSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ImportarFocosTest extends TestCase
{
    use RefreshDatabase;

    private const URL_DIARIA = 'https://dataserver-coids.inpe.br/queimadas/queimadas/focos/csv/diario/Brasil/focos_diario_br_20260915.csv';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MunicipioSeeder::class);
        Http::preventStrayRequests();
    }

    public function test_importa_apenas_os_focos_de_mato_grosso(): void
    {
        $this->fakeArquivoDiario();

        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        // 6 linhas no arquivo: 1 do PA, 1 do MS e 1 repetida
        $this->assertSame(3, Foco::count());
        $this->assertDatabaseMissing('focos', ['id' => '0b6f7f3e-1a2b-3c4d-8e9f-000000000004']);
        $this->assertDatabaseMissing('focos', ['id' => '0b6f7f3e-1a2b-3c4d-8e9f-000000000005']);
    }

    public function test_grava_os_campos_normalizados(): void
    {
        $this->fakeArquivoDiario();

        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        $foco = Foco::findOrFail('0b6f7f3e-1a2b-3c4d-8e9f-000000000002');
        $this->assertSame(-15.601, $foco->lat);
        $this->assertSame(-56.097, $foco->lon);
        $this->assertSame('2026-09-15 18:00:00', $foco->data_hora_gmt->format('Y-m-d H:i:s'));
        $this->assertSame('2026-09-15', $foco->data->format('Y-m-d'));
        $this->assertSame('GOES-19', $foco->satelite);
        $this->assertSame(5103403, $foco->municipio_id);
        $this->assertSame('Cuiabá', $foco->municipio->nome);
        $this->assertSame('Cerrado', $foco->bioma);
        $this->assertSame(40, $foco->numero_dias_sem_chuva);
        $this->assertSame(0.97, $foco->risco_fogo);
        $this->assertSame(120.5, $foco->frp);
    }

    public function test_valores_sem_dado_do_inpe_viram_nulos(): void
    {
        $this->fakeArquivoDiario();

        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        $foco = Foco::findOrFail('0b6f7f3e-1a2b-3c4d-8e9f-000000000003');
        $this->assertNull($foco->risco_fogo);
        $this->assertNull($foco->frp);
        $this->assertSame(5107800, $foco->municipio_id);
    }

    public function test_rodar_duas_vezes_nao_duplica_registros(): void
    {
        $this->fakeArquivoDiario();

        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();
        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        $this->assertSame(3, Foco::count());

        [$primeira, $segunda] = Importacao::orderBy('id')->get();
        $this->assertSame([3, 3, 0], [$primeira->registros, $primeira->novos, $primeira->atualizados]);
        $this->assertSame([3, 0, 3], [$segunda->registros, $segunda->novos, $segunda->atualizados]);
        $this->assertSame(Importacao::CONCLUIDA, $segunda->status);
    }

    public function test_reimportacao_atualiza_os_dados_do_foco(): void
    {
        $this->fakeArquivoDiario();
        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        Foco::whereKey('0b6f7f3e-1a2b-3c4d-8e9f-000000000001')->update(['frp' => 1]);
        $this->artisan('focos:importar', ['data' => '2026-09-15'])->assertSuccessful();

        $this->assertSame(62.1, Foco::findOrFail('0b6f7f3e-1a2b-3c4d-8e9f-000000000001')->frp);
    }

    public function test_aceita_hoje_e_ontem_como_data(): void
    {
        $this->travelTo(Carbon::parse('2026-09-16 10:00:00'));
        $this->fakeArquivoDiario();

        $this->artisan('focos:importar', ['data' => 'ontem'])->assertSuccessful();

        Http::assertSent(fn ($request) => $request->url() === self::URL_DIARIA);
    }

    public function test_rejeita_data_invalida(): void
    {
        $this->artisan('focos:importar', ['data' => '2026-02-30'])
            ->expectsOutputToContain('Data inválida')
            ->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_avisa_quando_o_arquivo_ainda_nao_foi_publicado(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        $this->artisan('focos:importar', ['data' => '2026-09-15'])
            ->expectsOutputToContain('Arquivo não encontrado')
            ->assertFailed();

        $this->assertSame(0, Foco::count());
    }

    public function test_opcao_fila_despacha_o_job(): void
    {
        Queue::fake();

        $this->artisan('focos:importar', ['data' => '2026-09-15', '--fila' => true])->assertSuccessful();

        Queue::assertPushed(ImportarFocosDiarios::class, fn ($job) => $job->data === '2026-09-15');
        Http::assertNothingSent();
    }

    public function test_importa_varios_dias_pulando_arquivos_ausentes(): void
    {
        $base = 'https://dataserver-coids.inpe.br/queimadas/queimadas/focos/csv/diario/Brasil/';
        $csv = file_get_contents(base_path('tests/fixtures/focos_diario_br_20260915.csv'));
        Http::fake([
            $base.'focos_diario_br_20260913.csv' => Http::response('', 404),
            $base.'focos_diario_br_20260914.csv' => fn () => Http::response($csv),
            $base.'focos_diario_br_20260915.csv' => fn () => Http::response($csv),
        ]);

        $this->artisan('focos:importar', ['data' => '2026-09-15', '--dias' => 3])
            ->expectsOutputToContain('focos_diario_br_20260913.csv')
            ->expectsTable(['Arquivo', 'Focos em MT', 'Novos', 'Atualizados'], [
                ['focos_diario_br_20260914.csv', 3, 3, 0],
                ['focos_diario_br_20260915.csv', 3, 0, 3],
                ['Total', 6, 3, 3],
            ])
            ->assertSuccessful();

        Http::assertSentCount(3);
        $this->assertSame(3, Foco::count());
    }

    public function test_rejeita_quantidade_de_dias_invalida(): void
    {
        $this->artisan('focos:importar', ['--dias' => 0])->assertExitCode(2);
        $this->artisan('focos:importar', ['--dias' => 'abc'])->assertExitCode(2);

        Http::assertNothingSent();
    }

    public function test_opcao_fila_com_dias_despacha_um_job_por_dia(): void
    {
        Queue::fake();

        $this->artisan('focos:importar', ['data' => '2026-09-15', '--dias' => 3, '--fila' => true])->assertSuccessful();

        Queue::assertPushed(ImportarFocosDiarios::class, 3);
        Queue::assertPushed(ImportarFocosDiarios::class, fn ($job) => $job->data === '2026-09-13');
    }

    public function test_job_ignora_arquivo_ainda_nao_publicado(): void
    {
        Http::fake(['*' => Http::response('Not Found', 404)]);

        (new ImportarFocosDiarios('2026-09-15'))->handle(app(ColetorFocos::class));

        $this->assertSame(0, Foco::count());
    }

    private function fakeArquivoDiario(): void
    {
        Http::fake([
            // closure: cada requisição recebe um corpo novo (o fake reaproveitaria o stream já lido)
            self::URL_DIARIA => fn () => Http::response(file_get_contents(base_path('tests/fixtures/focos_diario_br_20260915.csv'))),
        ]);
    }
}
