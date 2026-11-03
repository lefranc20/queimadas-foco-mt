<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Models\ClimaDiario;
use Carbon\CarbonImmutable;

class ClimaController extends Controller
{
    /**
     * A partir de 1 mm o dia conta como dia com chuva.
     */
    private const CHUVA_MINIMA_MM = 1;

    /**
     * GET /api/clima?inicio=&fim=
     *
     * Temperatura (média, mínima e máxima), umidade, chuva acumulada no período e dias
     * sem chuva até o fim do período, por município.
     */
    public function __invoke(FiltroFocosRequest $request): array
    {
        ['inicio' => $inicio, 'fim' => $fim] = $request->periodo();

        $municipios = ClimaDiario::query()
            ->toBase()
            ->join('municipios', 'municipios.id', '=', 'clima_diario.municipio_id')
            ->whereBetween('data', [$inicio, $fim])
            ->selectRaw('municipios.id, municipios.nome')
            ->selectRaw('avg(temperatura_media) as temperatura_media, min(temperatura_min) as temperatura_min, max(temperatura_max) as temperatura_max')
            ->selectRaw('avg(umidade_media) as umidade_media, sum(chuva_mm) as chuva_mm, max(data) as ultima_data, count(*) as dias')
            ->groupBy('municipios.id', 'municipios.nome')
            ->orderBy('municipios.nome')
            ->get();

        $ultimaChuva = ClimaDiario::query()
            ->where('chuva_mm', '>=', self::CHUVA_MINIMA_MM)
            ->where('data', '<=', $fim)
            ->groupBy('municipio_id')
            ->selectRaw('municipio_id, max(data) as data')
            ->pluck('data', 'municipio_id');

        $primeiroDia = ClimaDiario::query()->min('data');

        $cobertura = ClimaDiario::query()
            ->whereBetween('data', [$inicio, $fim])
            ->toBase()
            ->selectRaw('min(data) as inicio, max(data) as fim, count(distinct data) as dias')
            ->first();

        return [
            'periodo' => $request->periodo(),
            // o clima guardado pode cobrir só parte do período pedido
            'cobertura' => [
                'inicio' => $cobertura->inicio,
                'fim' => $cobertura->fim,
                'dias' => (int) $cobertura->dias,
                'parcial' => $cobertura->inicio === null || $cobertura->inicio > $inicio,
            ],
            'municipios' => $municipios->map(function (object $linha) use ($ultimaChuva, $primeiroDia) {
                $chuva = $ultimaChuva[$linha->id] ?? null;
                $ultimaData = CarbonImmutable::parse($linha->ultima_data);

                return [
                    'id' => (int) $linha->id,
                    'nome' => $linha->nome,
                    'temperatura_media' => round((float) $linha->temperatura_media, 1),
                    'temperatura_min' => round((float) $linha->temperatura_min, 1),
                    'temperatura_max' => round((float) $linha->temperatura_max, 1),
                    'umidade_media' => round((float) $linha->umidade_media),
                    'chuva_mm' => round((float) $linha->chuva_mm, 1),
                    'dias_sem_chuva' => (int) CarbonImmutable::parse($chuva ?? $primeiroDia)->diffInDays($ultimaData),
                    // sem chuva em todo o histórico guardado: o número real pode ser maior
                    'dias_sem_chuva_minimo' => $chuva === null,
                    'dias' => (int) $linha->dias,
                ];
            }),
        ];
    }
}
