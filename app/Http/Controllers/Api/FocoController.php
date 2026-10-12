<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Models\Foco;

class FocoController extends Controller
{
    private const LIMITE_PADRAO = 20000;

    /**
     * GET /api/focos?inicio=&fim=&municipio=&bioma=&satelite=&limite=
     *
     * Pontos para o mapa, do mais recente para o mais antigo. Se houver mais focos que o
     * limite, "truncado" vem verdadeiro e "total" informa quantos existem no filtro.
     */
    public function index(FiltroFocosRequest $request): array
    {
        $consulta = Foco::query()->filtrar($request->filtros());
        $limite = $request->integer('limite', self::LIMITE_PADRAO);
        $total = $consulta->clone()->count();

        $focos = $consulta->toBase()
            ->leftJoin('municipios', 'municipios.id', '=', 'focos.municipio_id')
            ->select([
                'focos.id', 'lat', 'lon', 'data_hora_gmt', 'satelite', 'municipio_id',
                'municipios.nome as municipio', 'bioma', 'numero_dias_sem_chuva', 'risco_fogo', 'frp',
            ])
            ->orderByDesc('data_hora_gmt')
            ->limit($limite)
            ->get()
            ->map(fn (object $foco) => [
                'id' => $foco->id,
                'lat' => (float) $foco->lat,
                'lon' => (float) $foco->lon,
                'data_hora_gmt' => (string) $foco->data_hora_gmt,
                'satelite' => $foco->satelite,
                'municipio_id' => $foco->municipio_id === null ? null : (int) $foco->municipio_id,
                'municipio' => $foco->municipio,
                'bioma' => $foco->bioma,
                'numero_dias_sem_chuva' => $foco->numero_dias_sem_chuva === null ? null : (int) $foco->numero_dias_sem_chuva,
                'risco_fogo' => $foco->risco_fogo === null ? null : (float) $foco->risco_fogo,
                'frp' => $foco->frp === null ? null : (float) $foco->frp,
            ]);

        return [
            'periodo' => $request->periodo(),
            'total' => $total,
            'limite' => $limite,
            'truncado' => $total > $limite,
            'focos' => $focos,
        ];
    }
}
