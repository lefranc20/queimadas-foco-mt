<?php

namespace App\Console\Commands;

use App\Services\Inpe\ColetorFocosRecentes;
use Illuminate\Console\Command;

class ImportarFocosRecentesCommand extends Command
{
    private const HORAS_MAXIMAS = 72;

    protected $signature = 'focos:recentes
        {--horas=3 : Quantas horas olhar para trás (o INPE mantém cerca de 3 dias nessa pasta)}';

    protected $description = 'Coleta os arquivos de 10 minutos do INPE e grava os focos recentes de Mato Grosso';

    public function handle(ColetorFocosRecentes $coletor): int
    {
        $horas = filter_var($this->option('horas'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::HORAS_MAXIMAS]]);

        if ($horas === false) {
            $this->error('Informe em --horas um número inteiro entre 1 e '.self::HORAS_MAXIMAS.'.');

            return self::INVALID;
        }

        $resultado = $coletor->coletar($horas);

        $this->info("{$resultado['arquivos']} arquivos importados, {$resultado['focos']} focos em MT ({$resultado['novos']} novos).");

        return self::SUCCESS;
    }
}
