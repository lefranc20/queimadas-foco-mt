<?php

use App\Jobs\ImportarClima;
use App\Jobs\ImportarFocosDiarios;
use App\Jobs\ImportarFocosRecentes;
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

// Alguns arquivos saem sem risco de fogo, chuva e dias sem chuva, de forma irregular.
// Reimportar dias recentes aproveita o que o INPE preencher depois (ele mantém cerca de 30 dias).
foreach ([2, 7, 14, 28] as $dias) {
    Schedule::job(new ImportarFocosDiarios(now('UTC')->subDays($dias)->toDateString()))
        ->dailyAt('10:10')
        ->timezone('America/Sao_Paulo')
        ->name("focos:reimportar-{$dias}-dias")
        ->withoutOverlapping();
}

// Focos quase em tempo real: pasta 10min/ do INPE, logo depois de cada publicação
Schedule::job(new ImportarFocosRecentes)
    ->cron('2-52/10 * * * *')
    ->name('focos:recentes')
    ->withoutOverlapping();

// Clima por município: dias já encerrados
Schedule::job(new ImportarClima)
    ->dailyAt('06:10')
    ->timezone('America/Sao_Paulo')
    ->name('clima:importar')
    ->withoutOverlapping();
