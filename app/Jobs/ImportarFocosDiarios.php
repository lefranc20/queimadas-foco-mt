<?php

namespace App\Jobs;

use App\Services\Inpe\ArquivoNaoEncontrado;
use App\Services\Inpe\ColetorFocos;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ImportarFocosDiarios implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /** @var list<int> */
    public array $backoff = [60, 300];

    /**
     * @param  string  $data  Data (GMT) no formato AAAA-MM-DD
     */
    public function __construct(public readonly string $data) {}

    public function handle(ColetorFocos $coletor): void
    {
        try {
            $importacao = $coletor->coletarDia(Carbon::parse($this->data));
        } catch (ArquivoNaoEncontrado $erro) {
            // O arquivo do dia ainda não foi publicado: não é falha, a próxima execução tenta de novo.
            Log::info($erro->getMessage());

            return;
        }

        Log::info("Focos de {$this->data} importados", $importacao->only('registros', 'novos', 'atualizados'));
    }
}
