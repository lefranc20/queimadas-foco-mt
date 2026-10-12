<?php

use App\Jobs\ImportarFocosDiarios;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Coleta automática dos focos do INPE
|--------------------------------------------------------------------------
|
| O INPE atualiza o CSV do dia corrente ao longo do dia e fecha o arquivo do
| dia anterior por volta das 09h (horário de Brasília). As datas são em GMT,
| como nos arquivos. Os jobs vão para a fila e são processados pelo worker.
|
*/

// Arquivo do dia corrente, que vai crescendo ao longo do dia
Schedule::job(new ImportarFocosDiarios(now('UTC')->toDateString()))
    ->hourlyAt(20)
    ->name('focos:importar-hoje')
    ->withoutOverlapping();

// Versão final do arquivo de ontem, depois que o INPE publica
Schedule::job(new ImportarFocosDiarios(now('UTC')->subDay()->toDateString()))
    ->dailyAt('09:40')
    ->timezone('America/Sao_Paulo')
    ->name('focos:importar-ontem')
    ->withoutOverlapping();
