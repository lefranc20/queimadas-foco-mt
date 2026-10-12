<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Services\EstatisticasFocos;

class SerieTemporalController extends Controller
{
    /**
     * Acima disso, sem "agrupar" informado, a série vem por mês.
     */
    private const MAXIMO_DIAS_AGRUPADO_POR_DIA = 92;

    /**
     * GET /api/serie-temporal?inicio=&fim=&agrupar=dia|mes&municipio=&bioma=&satelite=
     */
    public function __invoke(FiltroFocosRequest $request, EstatisticasFocos $estatisticas): array
    {
        $request->validate(['agrupar' => ['nullable', 'in:dia,mes']]);

        $dias = $request->inicio()->diffInDays($request->fim()) + 1;
        $agrupar = $request->input('agrupar') ?? ($dias > self::MAXIMO_DIAS_AGRUPADO_POR_DIA ? 'mes' : 'dia');

        return [
            'periodo' => $request->periodo(),
            'agrupar' => $agrupar,
            'serie' => $estatisticas->serie($request->filtros(), $agrupar),
        ];
    }
}
