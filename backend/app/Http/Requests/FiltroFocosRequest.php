<?php

namespace App\Http\Requests;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filtros comuns aos endpoints da API. Sem período informado, usa os últimos 30 dias.
 */
class FiltroFocosRequest extends FormRequest
{
    public const DIAS_PADRAO = 30;

    public const LIMITE_MAXIMO = 50000;

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'inicio' => ['nullable', 'date_format:Y-m-d'],
            'fim' => ['nullable', 'date_format:Y-m-d', Rule::when($this->filled('inicio'), 'after_or_equal:inicio')],
            'municipio' => ['nullable', 'integer'],
            'bioma' => ['nullable', 'string', 'max:30'],
            'satelite' => ['nullable', 'string', 'max:20'],
            'limite' => ['nullable', 'integer', 'min:1', 'max:'.self::LIMITE_MAXIMO],
        ];
    }

    public function fim(): CarbonImmutable
    {
        return $this->date('fim', 'Y-m-d', 'UTC')?->toImmutable() ?? CarbonImmutable::today('UTC');
    }

    public function inicio(): CarbonImmutable
    {
        return $this->date('inicio', 'Y-m-d', 'UTC')?->toImmutable()
            ?? $this->fim()->subDays(self::DIAS_PADRAO - 1);
    }

    /**
     * Filtros prontos para o scope Foco::filtrar().
     *
     * @return array{inicio: string, fim: string, municipio: ?int, bioma: ?string, satelite: ?string}
     */
    public function filtros(): array
    {
        return [
            'inicio' => $this->inicio()->toDateString(),
            'fim' => $this->fim()->toDateString(),
            'municipio' => $this->integer('municipio') ?: null,
            'bioma' => $this->string('bioma')->value() ?: null,
            'satelite' => $this->string('satelite')->value() ?: null,
        ];
    }

    /**
     * @return array{inicio: string, fim: string}
     */
    public function periodo(): array
    {
        return ['inicio' => $this->inicio()->toDateString(), 'fim' => $this->fim()->toDateString()];
    }
}
