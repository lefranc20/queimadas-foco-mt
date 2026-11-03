<?php

namespace App\Services;

use App\Models\ClimaDiario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Coleta temperatura, umidade e chuva diárias na sede de cada município.
 * Os valores vêm de modelos meteorológicos do Open-Meteo, não de estações locais.
 */
class ColetorClima
{
    private const LOCAIS_POR_CONSULTA = 50;

    public function __construct(
        private readonly LocalizadorMunicipio $localizador,
        private readonly string $url,
        private readonly int $timeout,
    ) {}

    /**
     * Importa os últimos dias completos (o dia corrente fica de fora por ainda ser previsão).
     *
     * @return int quantidade de registros (município + dia) gravados
     */
    public function coletar(int $dias): int
    {
        $hoje = CarbonImmutable::now('America/Cuiaba')->toDateString();
        $gravados = 0;

        foreach (array_chunk($this->localizador->sedes(), self::LOCAIS_POR_CONSULTA, true) as $sedes) {
            $resposta = Http::timeout($this->timeout)->retry(3, 2000)->get($this->url, [
                'latitude' => implode(',', array_column($sedes, 'lat')),
                'longitude' => implode(',', array_column($sedes, 'lon')),
                'daily' => 'temperature_2m_mean,temperature_2m_max,temperature_2m_min,relative_humidity_2m_mean,precipitation_sum',
                'past_days' => $dias,
                'forecast_days' => 1,
                'timezone' => 'America/Cuiaba',
            ])->throw()->json();

            $linhas = [];

            // com vários locais, a API devolve uma lista na mesma ordem da consulta
            foreach (array_keys($sedes) as $indice => $municipio) {
                $diario = $resposta[$indice]['daily'];

                foreach ($diario['time'] as $i => $data) {
                    if ($data >= $hoje) {
                        continue;
                    }

                    $linhas[] = [
                        'municipio_id' => $municipio,
                        'data' => $data,
                        'temperatura_media' => $diario['temperature_2m_mean'][$i],
                        'temperatura_min' => $diario['temperature_2m_min'][$i],
                        'temperatura_max' => $diario['temperature_2m_max'][$i],
                        'umidade_media' => $diario['relative_humidity_2m_mean'][$i],
                        'chuva_mm' => $diario['precipitation_sum'][$i],
                    ];
                }
            }

            foreach (array_chunk($linhas, 500) as $lote) {
                ClimaDiario::query()->upsert($lote, ['municipio_id', 'data'], [
                    'temperatura_media', 'temperatura_min', 'temperatura_max', 'umidade_media', 'chuva_mm',
                ]);
            }

            $gravados += count($linhas);
        }

        return $gravados;
    }
}
