<?php

namespace App\Services;

use App\Models\Foco;
use App\Models\FocoRecente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Transforma pontos de calor em uma leitura simples por município.
 *
 * Quantidade sozinha engana: o que indica um incêndio de verdade é a repetição no
 * mesmo lugar ao longo do tempo. As regras são deste projeto, não uma classificação
 * oficial do INPE.
 */
class SituacaoFocos
{
    public const ATENCAO = 'atencao';

    public const ACOMPANHAR = 'acompanhar';

    public const ISOLADO = 'isolado';

    public const SEM_DETECCAO = 'sem_deteccao';

    private const ORDEM = [self::ATENCAO => 0, self::ACOMPANHAR => 1, self::ISOLADO => 2];

    /**
     * Tamanho da célula, em graus (cerca de 2 km), para saber se o fogo voltou ao mesmo lugar.
     */
    private const CELULA = 0.02;

    private const FOCOS_ATENCAO = 10;

    private const LOCAIS_REPETIDOS_ATENCAO = 2;

    private const FOCOS_ACOMPANHAR = 3;

    private const DIAS_HISTORICO = 7;

    private const HORAS_MUNICIPIO = 24;

    public function __construct(private readonly IntensidadeFoco $intensidade) {}

    /**
     * Situação de cada município, do mais grave para o menos grave.
     *
     * @param  Collection<int, array<string, mixed>>  $focos  focos recentes, do mais novo para o mais antigo
     * @return Collection<int, array<string, mixed>>
     */
    public function porMunicipio(Collection $focos): Collection
    {
        $diasComFoco = $this->diasComFoco($focos->pluck('municipio_id')->unique()->all());

        return $focos->groupBy('municipio_id')
            ->map(function (Collection $grupo) use ($diasComFoco) {
                $municipio = $grupo->first();

                return [
                    'id' => $municipio['municipio_id'],
                    'nome' => $municipio['municipio'],
                    ...$this->analisar($grupo),
                    'dias_com_foco' => $diasComFoco[$municipio['municipio_id']] ?? 0,
                ];
            })
            ->sortBy([
                fn (array $a, array $b) => self::ORDEM[$a['nivel']] <=> self::ORDEM[$b['nivel']],
                fn (array $a, array $b) => $b['total'] <=> $a['total'],
            ])
            ->values();
    }

    /**
     * Resumo de um município, independente dos filtros da tela: últimas 24 horas e últimos 7 dias.
     *
     * @return array<string, mixed>
     */
    public function doMunicipio(int $municipio): array
    {
        $desde = CarbonImmutable::now('UTC')->subHours(self::HORAS_MUNICIPIO);

        $recentes = FocoRecente::query()
            ->toBase()
            ->where('municipio_id', $municipio)
            ->where('data_hora_gmt', '>=', $desde)
            ->orderByDesc('data_hora_gmt')
            ->get(['lat', 'lon', 'data_hora_gmt', 'satelite'])
            ->map(fn (object $foco) => [
                'lat' => (float) $foco->lat,
                'lon' => (float) $foco->lon,
                'data_hora_gmt' => substr((string) $foco->data_hora_gmt, 0, 19),
                'satelite' => $foco->satelite,
            ]);

        $semana = Foco::query()
            ->toBase()
            ->where('municipio_id', $municipio)
            ->where('data', '>=', $this->inicioDoHistorico())
            ->get(['data', 'satelite', 'frp']);

        $ultimos = array_filter([
            FocoRecente::query()->where('municipio_id', $municipio)->max('data_hora_gmt'),
            Foco::query()->where('municipio_id', $municipio)->max('data_hora_gmt'),
        ]);

        $analise = $recentes->isEmpty() ? null : $this->analisar($recentes);

        return [
            'nivel' => $analise['nivel'] ?? self::SEM_DETECCAO,
            'ultimo_foco' => $ultimos ? substr((string) max($ultimos), 0, 19) : null,
            'ultimas_24h' => $analise,
            'focos_7_dias' => $semana->count(),
            'dias_com_foco' => $semana->pluck('data')->map(fn ($data) => substr((string) $data, 0, 10))->unique()->count(),
            'intensidade' => $this->intensidade->maisForte($semana),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $focos  do mesmo município, do mais novo para o mais antigo
     * @return array<string, mixed>
     */
    private function analisar(Collection $focos): array
    {
        $ultima = $focos->first()['data_hora_gmt'];
        $primeira = $focos->last()['data_hora_gmt'];

        $repetidos = $focos
            ->groupBy(fn (array $foco) => round($foco['lat'] / self::CELULA).'_'.round($foco['lon'] / self::CELULA))
            ->filter(fn (Collection $celula) => $celula->pluck('data_hora_gmt')->unique()->count() >= 2)
            ->count();

        $latitude = deg2rad($focos->avg('lat'));

        return [
            'nivel' => $this->nivel($focos->count(), $repetidos),
            'total' => $focos->count(),
            'satelites' => $focos->pluck('satelite')->unique()->count(),
            'locais_repetidos' => $repetidos,
            'primeira_deteccao' => $primeira,
            'ultima_deteccao' => $ultima,
            'duracao_horas' => round(CarbonImmutable::parse($primeira)->diffInMinutes(CarbonImmutable::parse($ultima)) / 60, 1),
            'extensao_km' => [
                round(($focos->max('lat') - $focos->min('lat')) * 111, 1),
                round(($focos->max('lon') - $focos->min('lon')) * 111 * cos($latitude), 1),
            ],
        ];
    }

    private function nivel(int $focos, int $locaisRepetidos): string
    {
        if ($focos >= self::FOCOS_ATENCAO && $locaisRepetidos >= self::LOCAIS_REPETIDOS_ATENCAO) {
            return self::ATENCAO;
        }

        return $locaisRepetidos >= 1 || $focos >= self::FOCOS_ACOMPANHAR ? self::ACOMPANHAR : self::ISOLADO;
    }

    /**
     * @param  list<int>  $municipios
     * @return array<int, int>
     */
    private function diasComFoco(array $municipios): array
    {
        return Foco::query()
            ->toBase()
            ->whereIn('municipio_id', $municipios)
            ->where('data', '>=', $this->inicioDoHistorico())
            ->selectRaw('municipio_id, count(distinct data) as dias')
            ->groupBy('municipio_id')
            ->pluck('dias', 'municipio_id')
            ->map(fn ($dias) => (int) $dias)
            ->all();
    }

    private function inicioDoHistorico(): string
    {
        return CarbonImmutable::now('UTC')->subDays(self::DIAS_HISTORICO - 1)->toDateString();
    }
}
