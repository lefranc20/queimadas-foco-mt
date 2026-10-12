<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Municipio extends Model
{
    use HasFactory;

    /**
     * Grafias usadas pelo INPE que não batem com o nome oficial do IBGE.
     *
     * @var array<string, int>
     */
    public const ALIASES = [
        'SANTO ANTONIO DO LEVERGER' => 5107800, // IBGE: Santo Antônio de Leverger
    ];

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['id', 'nome', 'nome_normalizado'];

    protected static function booted(): void
    {
        static::saving(function (Municipio $municipio) {
            $municipio->nome_normalizado = self::normalizarNome($municipio->nome);
        });
    }

    /**
     * @return HasMany<Foco, $this>
     */
    public function focos(): HasMany
    {
        return $this->hasMany(Foco::class);
    }

    /**
     * "Santo Antônio de Leverger" e "SANTO ANTÔNIO DE LEVERGER" viram "SANTO ANTONIO DE LEVERGER".
     */
    public static function normalizarNome(string $nome): string
    {
        $nome = Str::upper(Str::ascii($nome));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $nome));
    }

    /**
     * Mapa "nome normalizado => código IBGE", incluindo os aliases.
     *
     * @return array<string, int>
     */
    public static function mapaPorNome(): array
    {
        return self::query()->pluck('id', 'nome_normalizado')->all() + self::ALIASES;
    }
}
