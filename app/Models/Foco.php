<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Foco extends Model
{
    use HasFactory, HasUuids;

    /**
     * Colunas que só existem nos arquivos diários e mensais (todos os satélites).
     */
    public const COLUNAS_METEOROLOGICAS = ['numero_dias_sem_chuva', 'precipitacao', 'risco_fogo', 'frp'];

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'data_hora_gmt' => 'datetime',
            'data' => 'date:Y-m-d',
            'municipio_id' => 'integer',
            'numero_dias_sem_chuva' => 'integer',
            'precipitacao' => 'float',
            'risco_fogo' => 'float',
            'frp' => 'float',
        ];
    }

    /**
     * @return BelongsTo<Municipio, $this>
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }

    /**
     * Aplica os filtros comuns da API. Chaves ausentes ou vazias são ignoradas.
     *
     * @param  array{inicio?: string, fim?: string, municipio?: int|string, bioma?: string, satelite?: string}  $filtros
     */
    #[Scope]
    protected function filtrar(Builder $query, array $filtros): void
    {
        $query
            ->when($filtros['inicio'] ?? null, fn (Builder $q, string $inicio) => $q->where('data', '>=', $inicio))
            ->when($filtros['fim'] ?? null, fn (Builder $q, string $fim) => $q->where('data', '<=', $fim))
            ->when($filtros['municipio'] ?? null, fn (Builder $q, $municipio) => $q->where('municipio_id', $municipio))
            ->when($filtros['bioma'] ?? null, fn (Builder $q, string $bioma) => $q->where('bioma', $bioma))
            ->when($filtros['satelite'] ?? null, fn (Builder $q, string $satelite) => $q->where('satelite', $satelite));
    }
}
