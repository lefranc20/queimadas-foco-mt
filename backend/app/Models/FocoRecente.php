<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FocoRecente extends Model
{
    protected $table = 'focos_recentes';

    protected $primaryKey = 'chave';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'lat' => 'float',
            'lon' => 'float',
            'data_hora_gmt' => 'datetime',
            'municipio_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Municipio, $this>
     */
    public function municipio(): BelongsTo
    {
        return $this->belongsTo(Municipio::class);
    }
}
