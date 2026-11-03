<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FocoRecente;
use App\Models\Importacao;
use App\Services\Inpe\ColetorFocosRecentes;
use App\Services\SituacaoFocos;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;

class FocoRecenteController extends Controller
{
    private const LIMITE = 20000;

    /**
     * GET /api/focos/recentes?horas=24
     *
     * Detecções das últimas horas (até 72), vindas dos arquivos de 10 minutos do INPE.
     * Não têm FRP, bioma nem dados meteorológicos, que só chegam no arquivo diário.
     */
    public function index(Request $request, SituacaoFocos $situacao): array
    {
        $validados = $request->validate(['horas' => ['nullable', 'integer', 'min:1', 'max:72']]);
        $horas = (int) ($validados['horas'] ?? 24);
        $desde = CarbonImmutable::now('UTC')->subHours($horas);

        $linhas = FocoRecente::query()
            ->toBase()
            ->join('municipios', 'municipios.id', '=', 'focos_recentes.municipio_id')
            ->where('data_hora_gmt', '>=', $desde)
            ->orderByDesc('data_hora_gmt')
            ->limit(self::LIMITE)
            ->get(['chave', 'lat', 'lon', 'data_hora_gmt', 'satelite', 'municipio_id', 'municipios.nome as municipio']);

        $focos = $linhas->map(fn (object $foco) => [
            'id' => $foco->chave,
            'lat' => (float) $foco->lat,
            'lon' => (float) $foco->lon,
            'data_hora_gmt' => substr((string) $foco->data_hora_gmt, 0, 19),
            'satelite' => $foco->satelite,
            'municipio_id' => (int) $foco->municipio_id,
            'municipio' => $foco->municipio,
            'bioma' => null,
            'numero_dias_sem_chuva' => null,
            'risco_fogo' => null,
            'frp' => null,
        ]);

        return [
            'horas' => $horas,
            'atualizado_em' => Importacao::query()
                ->where('tipo', ColetorFocosRecentes::TIPO)
                ->where('status', Importacao::CONCLUIDA)
                ->latest('concluida_em')
                ->first()?->concluida_em?->toIso8601String(),
            'total' => $focos->count(),
            'ultima_deteccao' => $focos->first()['data_hora_gmt'] ?? null,
            'municipios' => $situacao->porMunicipio($focos),
            'focos' => $focos,
        ];
    }
}
