<?php

namespace App\Console\Commands;

use App\Services\ColetorClima;
use Illuminate\Console\Command;

class ImportarClimaCommand extends Command
{
    private const DIAS_MAXIMOS = 92;

    protected $signature = 'clima:importar
        {--dias=7 : Quantos dias passados importar (o Open-Meteo aceita até 92)}';

    protected $description = 'Importa temperatura, umidade e chuva diárias dos municípios de Mato Grosso';

    public function handle(ColetorClima $coletor): int
    {
        $dias = filter_var($this->option('dias'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::DIAS_MAXIMOS]]);

        if ($dias === false) {
            $this->error('Informe em --dias um número inteiro entre 1 e '.self::DIAS_MAXIMOS.'.');

            return self::INVALID;
        }

        $this->info($coletor->coletar($dias).' registros de clima gravados.');

        return self::SUCCESS;
    }
}
