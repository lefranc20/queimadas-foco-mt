<?php

namespace App\Console\Commands;

use App\Jobs\ImportarFocosHistoricos;
use App\Models\Importacao;
use App\Services\Inpe\ArquivoNaoEncontrado;
use App\Services\Inpe\ColetorFocos;
use Illuminate\Console\Command;

class ImportarFocosHistorico extends Command
{
    /**
     * Primeiro ano com dados do satélite de referência para Mato Grosso no servidor do INPE.
     */
    private const PRIMEIRO_ANO = 2003;

    protected $signature = 'focos:importar-historico
        {ano : Ano a importar (ex.: 2024)}
        {--todos-satelites : Usa os arquivos mensais (todos os satélites) em vez do anual do satélite de referência}
        {--fila : Envia a importação para a fila em vez de executar agora}';

    protected $description = 'Importa o histórico de focos de Mato Grosso de um ano a partir dos arquivos anuais/mensais do INPE';

    public function handle(ColetorFocos $coletor): int
    {
        $ano = filter_var($this->argument('ano'), FILTER_VALIDATE_INT);

        if ($ano === false || $ano < self::PRIMEIRO_ANO || $ano > (int) now()->year) {
            $this->error('Informe um ano entre '.self::PRIMEIRO_ANO.' e '.now()->year.'.');

            return self::INVALID;
        }

        $todosSatelites = (bool) $this->option('todos-satelites');

        if ($this->option('fila')) {
            ImportarFocosHistoricos::dispatch($ano, $todosSatelites);
            $this->info("Importação do histórico de {$ano} enviada para a fila.");

            return self::SUCCESS;
        }

        try {
            $importacoes = $coletor->coletarAno($ano, $todosSatelites, fn (string $url) => $this->line("Baixando {$url}"));
        } catch (ArquivoNaoEncontrado $erro) {
            $this->warn($erro->getMessage());

            return self::FAILURE;
        }

        $this->table(
            ['Arquivo', 'Focos em MT', 'Novos', 'Atualizados'],
            array_map(fn (Importacao $i) => [$i->arquivo, $i->registros, $i->novos, $i->atualizados], $importacoes),
        );

        return self::SUCCESS;
    }
}
