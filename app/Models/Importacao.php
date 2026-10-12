<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Importacao extends Model
{
    public const EXECUTANDO = 'executando';

    public const CONCLUIDA = 'concluida';

    public const FALHOU = 'falhou';

    protected $table = 'importacoes';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'registros' => 'integer',
            'novos' => 'integer',
            'atualizados' => 'integer',
            'concluida_em' => 'datetime',
        ];
    }

    public static function ultimaConcluida(): ?self
    {
        return self::query()
            ->where('status', self::CONCLUIDA)
            ->latest('concluida_em')
            ->first();
    }
}
