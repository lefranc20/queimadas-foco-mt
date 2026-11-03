<?php

namespace App\Services;

/**
 * Descobre em qual município de MT está uma coordenada, usando a malha do IBGE.
 * Necessário para os arquivos de 10 minutos do INPE, que não trazem estado nem município.
 *
 * A malha é simplificada (qualidade mínima), então pontos colados na divisa
 * podem cair no município vizinho ou ficar de fora.
 */
class LocalizadorMunicipio
{
    /** @var list<array{id: int, caixa: array{float, float, float, float}, poligonos: list<list<list<array{float, float}>>>}> */
    private array $municipios = [];

    public function __construct(string $caminhoMalha, private readonly string $caminhoSedes)
    {
        $malha = json_decode(file_get_contents($caminhoMalha), true);

        foreach ($malha['features'] as $feature) {
            $geometria = $feature['geometry'];
            $poligonos = $geometria['type'] === 'Polygon' ? [$geometria['coordinates']] : $geometria['coordinates'];

            $lons = $lats = [];
            foreach ($poligonos as $poligono) {
                $lons = array_merge($lons, array_column($poligono[0], 0));
                $lats = array_merge($lats, array_column($poligono[0], 1));
            }

            $this->municipios[] = [
                'id' => (int) $feature['properties']['codarea'],
                'caixa' => [min($lons), min($lats), max($lons), max($lats)],
                'poligonos' => $poligonos,
            ];
        }
    }

    /**
     * Código IBGE do município que contém o ponto, ou null se estiver fora de MT.
     */
    public function localizar(float $lat, float $lon): ?int
    {
        foreach ($this->municipios as $municipio) {
            [$oeste, $sul, $leste, $norte] = $municipio['caixa'];

            if ($lon < $oeste || $lon > $leste || $lat < $sul || $lat > $norte) {
                continue;
            }

            foreach ($municipio['poligonos'] as $poligono) {
                if ($this->contem($poligono, $lat, $lon)) {
                    return $municipio['id'];
                }
            }
        }

        return null;
    }

    /**
     * Ponto de consulta do clima: a sede de cada município, onde as pessoas vivem e o clima
     * é percebido. O centro do território pode cair em área rural, em outra altitude.
     * Sem sede conhecida, usa o centro da caixa do município.
     *
     * @return array<int, array{lat: float, lon: float}>
     */
    public function sedes(): array
    {
        $sedes = [];

        foreach ($this->municipios as $municipio) {
            [$oeste, $sul, $leste, $norte] = $municipio['caixa'];
            $sedes[$municipio['id']] = ['lat' => round(($sul + $norte) / 2, 4), 'lon' => round(($oeste + $leste) / 2, 4)];
        }

        return array_replace($sedes, json_decode(file_get_contents($this->caminhoSedes), true));
    }

    /**
     * Regra par-ímpar: conta quantas bordas um raio horizontal cruza. Inclui os anéis internos (buracos).
     *
     * @param  list<list<array{float, float}>>  $aneis
     */
    private function contem(array $aneis, float $lat, float $lon): bool
    {
        $dentro = false;

        foreach ($aneis as $anel) {
            $anterior = end($anel);

            foreach ($anel as $vertice) {
                [$x1, $y1] = $vertice;
                [$x2, $y2] = $anterior;

                if (($y1 > $lat) !== ($y2 > $lat) && $lon < ($x2 - $x1) * ($lat - $y1) / ($y2 - $y1) + $x1) {
                    $dentro = ! $dentro;
                }

                $anterior = $vertice;
            }
        }

        return $dentro;
    }
}
