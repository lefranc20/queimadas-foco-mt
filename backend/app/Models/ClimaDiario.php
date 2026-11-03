<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClimaDiario extends Model
{
    protected $table = 'clima_diario';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $guarded = [];
}
