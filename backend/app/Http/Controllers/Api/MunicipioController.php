<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Models\Foco;
use App\Models\Municipio;
use App\Services\EstatisticasFocos;
use Illuminate\Support\Arr;

class MunicipioController extends Controller
{
    /**
     * GET /api/municipios
     */
    public function index(): array
    {
        return Municipio::query()->orderBy('nome')->get(['id', 'nome'])->toArray();
    }

    /**
     * GET /api/municipios/{id}?inicio=&fim=&bioma=&satelite=
     *
     * Totais do município no período, posição no ranking estadual, histórico por ano
     * e a série mensal do ano de "fim" comparada com o ano anterior.
     */
    public function show(Municipio $municipio, FiltroFocosRequest $request, EstatisticasFocos $estatisticas): array
    {
        $filtrosEstado = Arr::except($request->filtros(), ['municipio']);
        $filtros = [...$filtrosEstado, 'municipio' => $municipio->id];

        $ranking = $estatisticas->ranking($filtrosEstado);
        $posicao = array_search($municipio->id, array_column($ranking, 'id'), true);

        $fim = $request->fim();
        $porAno = $estatisticas->porAno(Arr::except($filtros, ['inicio', 'fim']));

        $medias = Foco::query()
            ->filtrar($filtros)
            ->toBase()
            ->selectRaw('avg(numero_dias_sem_chuva) as dias_sem_chuva, avg(risco_fogo) as risco_fogo, avg(frp) as frp')
            ->first();

        return [
            'id' => $municipio->id,
            'nome' => $municipio->nome,
            'periodo' => $request->periodo(),
            'total' => $estatisticas->total($filtros),
            'posicao_ranking' => $posicao === false ? null : $posicao + 1,
            'municipios_com_focos' => count($ranking),
            'medias' => [
                'dias_sem_chuva' => $medias->dias_sem_chuva === null ? null : round((float) $medias->dias_sem_chuva, 1),
                'risco_fogo' => $medias->risco_fogo === null ? null : round((float) $medias->risco_fogo, 2),
                'frp' => $medias->frp === null ? null : round((float) $medias->frp, 1),
            ],
            'biomas' => Foco::query()
                ->filtrar($filtros)
                ->toBase()
                ->selectRaw('bioma, count(*) as total')
                ->groupBy('bioma')
                ->orderByDesc('total')
                ->get()
                ->map(fn (object $linha) => ['bioma' => $linha->bioma, 'total' => (int) $linha->total]),
            'por_ano' => array_map(
                fn (int $ano, int $total) => ['ano' => $ano, 'total' => $total],
                array_keys($porAno),
                $porAno,
            ),
            'serie_mensal' => $estatisticas->serie(
                [...$filtros, 'inicio' => $fim->startOfYear()->toDateString(), 'fim' => $fim->toDateString()],
                'mes',
            ),
        ];
    }
}
