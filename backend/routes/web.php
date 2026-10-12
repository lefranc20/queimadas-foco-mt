<?php

use Illuminate\Support\Facades\Route;

// O backend é só a API; o painel fica no projeto frontend.
Route::get('/', fn () => [
    'nome' => config('app.name'),
    'api' => url('/api'),
    'endpoints' => [
        'focos' => url('/api/focos'),
        'ranking' => url('/api/municipios/ranking'),
        'serie_temporal' => url('/api/serie-temporal'),
        'resumo' => url('/api/resumo'),
        'municipios' => url('/api/municipios'),
        'metadados' => url('/api/metadados'),
    ],
]);
