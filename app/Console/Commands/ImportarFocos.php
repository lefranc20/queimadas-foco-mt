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
        {--fila : Envia a importação para a fila em vez de executar agora}';

    protected $description = 'Baixa o CSV diário de focos do INPE e grava os focos de Mato Grosso';

    public function handle(ColetorFocos $coletor): int
    {
        try {
            $data = $this->interpretarData($this->argument('data'));
        } catch (InvalidArgumentException $erro) {
            $this->error($erro->getMessage());

            return self::INVALID;
        }

        if ($this->option('fila')) {
            ImportarFocosDiarios::dispatch($data->toDateString());
            $this->info("Importação de {$data->toDateString()} enviada para a fila.");

            return self::SUCCESS;
        }

        try {
            $importacao = $coletor->coletarDia($data, fn (string $url) => $this->line("Baixando {$url}"));
        } catch (ArquivoNaoEncontrado $erro) {
            $this->warn($erro->getMessage());

            return self::FAILURE;
        }

        $this->exibirResultado($importacao);

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

    private function exibirResultado(Importacao $importacao): void
    {
        $this->table(
            ['Arquivo', 'Focos em MT', 'Novos', 'Atualizados'],
            [[$importacao->arquivo, $importacao->registros, $importacao->novos, $importacao->atualizados]],
        );
    }
}
