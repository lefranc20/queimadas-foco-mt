<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Foco;
use App\Models\Importacao;

class MetadadosController extends Controller
{
    /**
     * GET /api/metadados
     *
     * Opções para os filtros do painel e o período que existe no banco.
     */
    public function __invoke(): array
    {
        return [
            'satelite_referencia' => config('inpe.satelite_referencia'),
            'satelites' => Foco::query()->distinct()->orderBy('satelite')->pluck('satelite'),
            'biomas' => Foco::query()->whereNotNull('bioma')->distinct()->orderBy('bioma')->pluck('bioma'),
            'periodo_disponivel' => [
                'inicio' => Foco::query()->min('data'),
                'fim' => Foco::query()->max('data'),
            ],
            'ultima_atualizacao' => Importacao::ultimaConcluida()?->concluida_em?->toIso8601String(),
        ];
    }
}
