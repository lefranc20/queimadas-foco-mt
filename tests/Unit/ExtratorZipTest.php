<?php

namespace Tests\Unit;

use App\Services\Inpe\ExtratorZip;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ExtratorZipTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../fixtures';

    public function test_extrai_csv_compactado_com_deflate_ignorando_outros_arquivos(): void
    {
        $csv = (new ExtratorZip)->extrairCsv(self::FIXTURES.'/focos_br_mt_ref_2024.zip');

        $conteudo = file_get_contents($csv);
        unlink($csv);

        $this->assertStringStartsWith('id_bdq,foco_id,lat,lon,data_pas', $conteudo);
        $this->assertStringContainsString('SANTO ANTÔNIO DO LEVERGER', $conteudo);
        $this->assertSame(5, substr_count($conteudo, "\n"));
    }

    public function test_extrai_csv_sem_compressao_dentro_de_subpasta(): void
    {
        $csv = (new ExtratorZip)->extrairCsv(self::FIXTURES.'/focos_sem_compressao.zip');

        $conteudo = file_get_contents($csv);
        unlink($csv);

        $this->assertStringStartsWith('id_bdq,foco_id', $conteudo);
    }

    public function test_recusa_arquivo_que_nao_e_zip(): void
    {
        $this->expectException(RuntimeException::class);

        (new ExtratorZip)->extrairCsv(self::FIXTURES.'/focos_diario_br_20260915.csv');
    }
}
