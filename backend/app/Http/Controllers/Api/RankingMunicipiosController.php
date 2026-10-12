<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Services\EstatisticasFocos;

class RankingMunicipiosController extends Controller
{
    private const LIMITE_PADRAO = 10;

    /**
     * GET /api/municipios/ranking?inicio=&fim=&bioma=&satelite=&limite=
     */
    public function __invoke(FiltroFocosRequest $request, EstatisticasFocos $estatisticas): array
    {
        $filtros = $request->filtros();

        return [
            'periodo' => $request->periodo(),
            'total' => $estatisticas->total($filtros),
            'municipios' => $estatisticas->ranking($filtros, $request->integer('limite', self::LIMITE_PADRAO)),
        ];
    }
}
