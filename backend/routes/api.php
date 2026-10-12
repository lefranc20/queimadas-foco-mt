<?php

use App\Http\Controllers\Api\FocoController;
use App\Http\Controllers\Api\MetadadosController;
use App\Http\Controllers\Api\MunicipioController;
use App\Http\Controllers\Api\RankingMunicipiosController;
use App\Http\Controllers\Api\ResumoController;
use App\Http\Controllers\Api\SerieTemporalController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:120,1')->group(function () {
    Route::get('focos', [FocoController::class, 'index']);
    Route::get('serie-temporal', SerieTemporalController::class);
    Route::get('resumo', ResumoController::class);
    Route::get('metadados', MetadadosController::class);

    Route::get('municipios', [MunicipioController::class, 'index']);
    Route::get('municipios/ranking', RankingMunicipiosController::class);
    Route::get('municipios/{municipio}', [MunicipioController::class, 'show'])->whereNumber('municipio');
});
