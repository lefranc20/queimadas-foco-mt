<?php

namespace App\Console\Commands;

use App\Jobs\ImportarFocosDiarios;
use App\Models\Importacao;
use App\Services\Inpe\ArquivoNaoEncontrado;
use App\Services\Inpe\ColetorFocos;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use InvalidArgumentException;

class ImportarFocos extends Command
{
    protected $signature = 'focos:importar
        {data=hoje : Data GMT do arquivo diário: AAAA-MM-DD, "hoje" ou "ontem"}
        {--dias=1 : Quantos dias importar, terminando na data informada (o INPE mantém cerca de 30 dias)}
        {--fila : Envia a importação para a fila em vez de executar agora}';

    protected $description = 'Baixa o CSV diário de focos do INPE e grava os focos de Mato Grosso';

    public function handle(ColetorFocos $coletor): int
    {
        try {
            $data = $this->interpretarData($this->argument('data'));
            $dias = $this->interpretarDias($this->option('dias'));
        } catch (InvalidArgumentException $erro) {
            $this->error($erro->getMessage());

            return self::INVALID;
        }

        // do dia mais antigo para o mais recente
        $datas = array_map(fn (int $i) => $data->subDays($i), range($dias - 1, 0));

        if ($this->option('fila')) {
            foreach ($datas as $dia) {
                ImportarFocosDiarios::dispatch($dia->toDateString());
            }
            $this->info(count($datas) === 1
                ? "Importação de {$data->toDateString()} enviada para a fila."
                : count($datas).' importações enviadas para a fila.');

            return self::SUCCESS;
        }

        $importacoes = [];

        foreach ($datas as $dia) {
            try {
                $importacoes[] = $coletor->coletarDia($dia, fn (string $url) => $this->line("Baixando {$url}"));
            } catch (ArquivoNaoEncontrado $erro) {
                $this->warn($erro->getMessage());
            }
        }

        if (! $importacoes) {
            return self::FAILURE;
        }

        $this->exibirResultado($importacoes);

        return self::SUCCESS;
    }

    private function interpretarData(string $valor): CarbonImmutable
    {
        $hoje = CarbonImmutable::now('UTC')->startOfDay();

        return match ($valor) {
            'hoje' => $hoje,
            'ontem' => $hoje->subDay(),
            default => $this->dataValida($valor) ?? throw new InvalidArgumentException(
                "Data inválida: \"{$valor}\". Use AAAA-MM-DD, \"hoje\" ou \"ontem\"."
            ),
        };
    }

    private function dataValida(string $valor): ?CarbonImmutable
    {
        $data = CarbonImmutable::createFromFormat('!Y-m-d', $valor, 'UTC');

        return $data && $data->format('Y-m-d') === $valor ? $data : null;
    }

    private function interpretarDias(mixed $valor): int
    {
        $dias = filter_var($valor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 366]]);

        return $dias ?: throw new InvalidArgumentException('Informe em --dias um número inteiro entre 1 e 366.');
    }

    /**
     * @param  list<Importacao>  $importacoes
     */
    private function exibirResultado(array $importacoes): void
    {
        $linhas = array_map(fn (Importacao $i) => [$i->arquivo, $i->registros, $i->novos, $i->atualizados], $importacoes);

        if (count($importacoes) > 1) {
            $linhas[] = [
                'Total',
                array_sum(array_column($linhas, 1)),
                array_sum(array_column($linhas, 2)),
                array_sum(array_column($linhas, 3)),
            ];
        }

        $this->table(['Arquivo', 'Focos em MT', 'Novos', 'Atualizados'], $linhas);
    }
}
