<?php

namespace App\Services;

use App\Models\Foco;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Agregações usadas pelos endpoints da API.
 *
 * As contagens são agrupadas por data no banco e, quando preciso, por mês ou ano em PHP.
 * Assim as mesmas consultas funcionam em SQLite e PostgreSQL sem funções de data
 * específicas de cada banco.
 */
class EstatisticasFocos
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function total(array $filtros): int
    {
        return Foco::query()->filtrar($filtros)->count();
    }

    /**
     * Contagem e somas por dia (as somas permitem calcular médias exatas ao agrupar por mês).
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<string, object>
     */
    public function porDia(array $filtros): Collection
    {
        return Foco::query()
            ->filtrar($filtros)
            ->toBase()
            ->selectRaw('data, count(*) as total')
            ->selectRaw('sum(numero_dias_sem_chuva) as soma_dias, count(numero_dias_sem_chuva) as qtd_dias')
            ->selectRaw('sum(risco_fogo) as soma_risco, count(risco_fogo) as qtd_risco')
            ->groupBy('data')
            ->get()
            ->keyBy(fn (object $linha) => substr((string) $linha->data, 0, 10));
    }

    /**
     * Total de focos por ano.
     *
     * @param  array<string, mixed>  $filtros
     * @return array<int, int>
     */
    public function porAno(array $filtros): array
    {
        $anos = [];

        foreach ($this->porDia($filtros) as $data => $linha) {
            $ano = (int) substr($data, 0, 4);
            $anos[$ano] = ($anos[$ano] ?? 0) + (int) $linha->total;
        }

        ksort($anos);

        return $anos;
    }

    /**
     * Série temporal do período (com zeros nos dias sem foco) e o total do mesmo período
     * no ano anterior, alinhado na mesma data para facilitar o gráfico comparativo.
     *
     * @param  array<string, mixed>  $filtros  precisa conter "inicio" e "fim"
     * @param  'dia'|'mes'  $agrupar
     * @return list<array{periodo: string, total: int, total_ano_anterior: int, media_dias_sem_chuva: ?float, media_risco_fogo: ?float}>
     */
    public function serie(array $filtros, string $agrupar): array
    {
        $inicio = CarbonImmutable::parse($filtros['inicio']);
        $fim = CarbonImmutable::parse($filtros['fim']);

        $atual = $this->porDia($filtros);
        $anterior = $this->porDia([
            ...$filtros,
            'inicio' => $inicio->subYearNoOverflow()->toDateString(),
            'fim' => $fim->subYearNoOverflow()->toDateString(),
        ]);

        $pontos = [];

        foreach (CarbonPeriod::create($inicio, $fim) as $dia) {
            $periodo = $agrupar === 'mes' ? $dia->format('Y-m') : $dia->toDateString();
            $ponto = $pontos[$periodo] ?? ['total' => 0, 'total_ano_anterior' => 0, 'soma_dias' => 0, 'qtd_dias' => 0, 'soma_risco' => 0, 'qtd_risco' => 0];

            if ($linha = $atual->get($dia->toDateString())) {
                $ponto['total'] += (int) $linha->total;
                $ponto['soma_dias'] += (float) $linha->soma_dias;
                $ponto['qtd_dias'] += (int) $linha->qtd_dias;
                $ponto['soma_risco'] += (float) $linha->soma_risco;
                $ponto['qtd_risco'] += (int) $linha->qtd_risco;
            }

            $diaAnterior = CarbonImmutable::instance($dia)->subYearNoOverflow()->toDateString();
            $ponto['total_ano_anterior'] += (int) ($anterior->get($diaAnterior)->total ?? 0);

            $pontos[$periodo] = $ponto;
        }

        return array_map(fn (string $periodo, array $p) => [
            'periodo' => $periodo,
            'total' => $p['total'],
            'total_ano_anterior' => $p['total_ano_anterior'],
            'media_dias_sem_chuva' => $p['qtd_dias'] ? round($p['soma_dias'] / $p['qtd_dias'], 1) : null,
            'media_risco_fogo' => $p['qtd_risco'] ? round($p['soma_risco'] / $p['qtd_risco'], 2) : null,
        ], array_keys($pontos), $pontos);
    }

    /**
     * Municípios com mais focos no período.
     *
     * @param  array<string, mixed>  $filtros
     * @return list<array{id: int, nome: string, total: int, percentual: float}>
     */
    public function ranking(array $filtros, ?int $limite = null): array
    {
        $total = $this->total($filtros);

        return Foco::query()
            ->filtrar($filtros)
            ->toBase()
            ->join('municipios', 'municipios.id', '=', 'focos.municipio_id')
            ->selectRaw('municipios.id, municipios.nome, count(*) as total')
            ->groupBy('municipios.id', 'municipios.nome')
            ->orderByDesc('total')
            ->orderBy('municipios.nome')
            ->when($limite, fn ($query) => $query->limit($limite))
            ->get()
            ->map(fn (object $linha) => [
                'id' => (int) $linha->id,
                'nome' => $linha->nome,
                'total' => (int) $linha->total,
                'percentual' => $total ? round($linha->total / $total * 100, 1) : 0.0,
            ])
            ->all();
    }

    /**
     * Comparação de um período com o mesmo período do ano anterior.
     *
     * @param  array<string, mixed>  $filtros
     * @return array{inicio: string, fim: string, total: int, ano_anterior: int, variacao: ?float}
     */
    public function comparativo(array $filtros, CarbonImmutable $inicio, CarbonImmutable $fim): array
    {
        $atual = $this->total([...$filtros, 'inicio' => $inicio->toDateString(), 'fim' => $fim->toDateString()]);
        $anterior = $this->total([
            ...$filtros,
            'inicio' => $inicio->subYearNoOverflow()->toDateString(),
            'fim' => $fim->subYearNoOverflow()->toDateString(),
        ]);

        return [
            'inicio' => $inicio->toDateString(),
            'fim' => $fim->toDateString(),
            'total' => $atual,
            'ano_anterior' => $anterior,
            'variacao' => $anterior === 0 ? null : round(($atual - $anterior) / $anterior * 100, 1),
        ];
    }
}
