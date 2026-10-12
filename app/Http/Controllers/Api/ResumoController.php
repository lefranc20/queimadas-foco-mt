<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FiltroFocosRequest;
use App\Models\Importacao;
use App\Services\EstatisticasFocos;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

class ResumoController extends Controller
{
    /**
     * GET /api/resumo?satelite=&bioma=&municipio=
     *
     * Focos de hoje, do mês e do ano (até hoje), cada um comparado com o mesmo
     * período do ano anterior. Datas em GMT, como nos arquivos do INPE.
     */
    public function __invoke(FiltroFocosRequest $request, EstatisticasFocos $estatisticas): array
    {
        $filtros = Arr::except($request->filtros(), ['inicio', 'fim']);
        $hoje = CarbonImmutable::today('UTC');

        return [
            'data_referencia' => $hoje->toDateString(),
            'hoje' => $estatisticas->comparativo($filtros, $hoje, $hoje),
            'mes' => $estatisticas->comparativo($filtros, $hoje->startOfMonth(), $hoje),
            'ano' => $estatisticas->comparativo($filtros, $hoje->startOfYear(), $hoje),
            'ultima_atualizacao' => Importacao::ultimaConcluida()?->concluida_em?->toIso8601String(),
        ];
    }
}
