<?php

namespace Tests\Unit;

use App\Services\Inpe\LeitorCsvFocos;
use PHPUnit\Framework\TestCase;

class LeitorCsvFocosTest extends TestCase
{
    private function ler(string $conteudo): array
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'teste_');
        file_put_contents($arquivo, $conteudo);

        try {
            return iterator_to_array((new LeitorCsvFocos('MATO GROSSO', 'AQUA_M-T'))->ler($arquivo), false);
        } finally {
            unlink($arquivo);
        }
    }

    public function test_le_layout_diario_e_filtra_o_estado(): void
    {
        $focos = $this->ler(file_get_contents(__DIR__.'/../fixtures/focos_diario_br_20260915.csv'));

        // a linha repetida continua aqui; quem remove duplicatas é o upsert
        $this->assertCount(4, $focos);
        $this->assertSame([
            'id' => '0b6f7f3e-1a2b-3c4d-8e9f-000000000001',
            'lat' => -12.2343,
            'lon' => -57.1271,
            'data_hora_gmt' => '2026-09-15 17:30:00',
            'data' => '2026-09-15',
            'satelite' => 'AQUA_M-T',
            'municipio_id' => 5108907,
            'municipio' => 'NOVA MARINGÁ',
            'bioma' => 'Amazônia',
            'numero_dias_sem_chuva' => 25,
            'precipitacao' => 0.0,
            'risco_fogo' => 1.0,
            'frp' => 62.1,
        ], $focos[0]);
    }

    public function test_le_layout_anual_preenchendo_o_satelite_de_referencia(): void
    {
        $focos = $this->ler(
            "\u{FEFF}id_bdq,foco_id,lat,lon,data_pas,pais,estado,municipio,bioma\n"
            ." 1666547680 ,1c0a0000-0000-3000-8000-000000000002,  -17.490430 ,  -54.685280 ,2024-01-05 17:30:00,Brasil,MATO GROSSO,ITIQUIRA,Cerrado\n"
        );

        $this->assertSame([[
            'id' => '1c0a0000-0000-3000-8000-000000000002',
            'lat' => -17.49043,
            'lon' => -54.68528,
            'data_hora_gmt' => '2024-01-05 17:30:00',
            'data' => '2024-01-05',
            'satelite' => 'AQUA_M-T',
            'municipio_id' => null,
            'municipio' => 'ITIQUIRA',
            'bioma' => 'Cerrado',
        ]], $focos);
    }

    public function test_arquivo_vazio_nao_gera_focos(): void
    {
        $this->assertSame([], $this->ler(''));
    }
}
