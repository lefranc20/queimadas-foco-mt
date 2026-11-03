<?php

namespace App\Jobs;

use App\Services\Inpe\ColetorFocosRecentes;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ImportarFocosRecentes implements ShouldQueue
{
    use Queueable;

    // Se falhar, a próxima execução (10 minutos depois) recupera os arquivos pendentes.
    public int $tries = 1;

    public int $timeout = 80;

    public function __construct(public readonly int $horas = 3) {}

    public function handle(ColetorFocosRecentes $coletor): void
    {
        $coletor->coletar($this->horas);
    }
}
