<?php

namespace App\Jobs;

use App\Services\Inpe\ColetorFocos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ImportarFocosHistoricos implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(
        public readonly int $ano,
        public readonly bool $todosSatelites = false,
    ) {}

    public function handle(ColetorFocos $coletor): void
    {
        foreach ($coletor->coletarAno($this->ano, $this->todosSatelites) as $importacao) {
            Log::info("Histórico importado: {$importacao->arquivo}", $importacao->only('registros', 'novos', 'atualizados'));
        }
    }
}
