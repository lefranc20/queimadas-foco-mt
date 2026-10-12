<?php

namespace App\Services\Inpe;

use Generator;
use RuntimeException;

/**
 * Lê os CSVs de focos do INPE linha a linha e devolve só os focos do estado configurado,
 * já normalizados para as colunas da tabela "focos".
 *
 * O INPE publica dois layouts:
 *
 * - Diário/mensal (todos os satélites):
 *   id, lat, lon, data_hora_gmt, satelite, municipio, estado, pais, municipio_id, estado_id,
 *   pais_id, numero_dias_sem_chuva, precipitacao, risco_fogo, bioma, frp
 *
 * - Anual do satélite de referência (sat_ref):
 *   id_bdq, foco_id, lat, lon, data_pas, pais, estado, municipio, bioma
 *
 * O "foco_id" do layout anual é o mesmo UUID da coluna "id" do diário, o que permite
 * fazer upsert das duas fontes na mesma tabela sem duplicar focos.
 */
class LeitorCsvFocos
{
    /**
     * Valor que o INPE usa quando não há dado (ex.: risco_fogo = -999.0).
     */
    private const SEM_DADO = -999;

    public function __construct(
        private readonly string $estado,
        private readonly string $sateliteReferencia,
    ) {}

    /**
     * @return Generator<int, array<string, mixed>>
     */
    public function ler(string $caminho): Generator
    {
        $arquivo = @fopen($caminho, 'rb') ?: throw new RuntimeException("Não foi possível abrir {$caminho}");

        try {
            $cabecalho = fgetcsv($arquivo, escape: '');
            if (! $cabecalho) {
                return;
            }

            $cabecalho = array_map(fn (string $coluna) => trim(str_replace("\u{FEFF}", '', $coluna)), $cabecalho);
            $layoutReferencia = in_array('foco_id', $cabecalho, true);
            $totalColunas = count($cabecalho);

            while (($valores = fgetcsv($arquivo, escape: '')) !== false) {
                if (count($valores) !== $totalColunas) {
                    continue; // linha em branco ou quebrada
                }

                $linha = array_combine($cabecalho, array_map('trim', $valores));

                if ($linha['estado'] !== $this->estado) {
                    continue;
                }

                yield $layoutReferencia ? $this->doLayoutReferencia($linha) : $this->doLayoutPadrao($linha);
            }
        } finally {
            fclose($arquivo);
        }
    }

    /**
     * @param  array<string, string>  $linha
     * @return array<string, mixed>
     */
    private function doLayoutPadrao(array $linha): array
    {
        $diasSemChuva = $this->numero($linha['numero_dias_sem_chuva']);

        return [
            'id' => $linha['id'],
            'lat' => (float) $linha['lat'],
            'lon' => (float) $linha['lon'],
            'data_hora_gmt' => $linha['data_hora_gmt'],
            'data' => substr($linha['data_hora_gmt'], 0, 10),
            'satelite' => $linha['satelite'],
            'municipio_id' => (int) $linha['municipio_id'] ?: null,
            'municipio' => $linha['municipio'],
            'bioma' => $linha['bioma'] ?: null,
            'numero_dias_sem_chuva' => $diasSemChuva === null ? null : (int) round($diasSemChuva),
            'precipitacao' => $this->numero($linha['precipitacao']),
            'risco_fogo' => $this->numero($linha['risco_fogo']),
            'frp' => $this->numero($linha['frp']),
        ];
    }

    /**
     * O layout anual não traz satélite nem código do município: o satélite é sempre o de
     * referência e o município é resolvido pelo nome na importação.
     *
     * @param  array<string, string>  $linha
     * @return array<string, mixed>
     */
    private function doLayoutReferencia(array $linha): array
    {
        return [
            'id' => $linha['foco_id'],
            'lat' => (float) $linha['lat'],
            'lon' => (float) $linha['lon'],
            'data_hora_gmt' => $linha['data_pas'],
            'data' => substr($linha['data_pas'], 0, 10),
            'satelite' => $this->sateliteReferencia,
            'municipio_id' => null,
            'municipio' => $linha['municipio'],
            'bioma' => $linha['bioma'] ?: null,
        ];
    }

    private function numero(string $valor): ?float
    {
        if ($valor === '' || ! is_numeric($valor)) {
            return null;
        }

        $numero = (float) $valor;

        return $numero <= self::SEM_DADO ? null : $numero;
    }
}
