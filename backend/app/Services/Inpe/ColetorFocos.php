<?php

namespace App\Services\Inpe;

use App\Models\Importacao;
use Carbon\CarbonInterface;
use Closure;

/**
 * Decide quais arquivos do INPE baixar para um dia ou um ano e repassa cada um ao importador.
 */
class ColetorFocos
{
    public function __construct(
        private readonly ClienteInpe $cliente,
        private readonly ExtratorZip $extrator,
        private readonly ImportadorFocos $importador,
    ) {}

    /**
     * Importa o CSV diário (todos os satélites). O arquivo do dia corrente é atualizado
     * pelo INPE ao longo do dia, por isso pode ser importado mais de uma vez.
     *
     * @param  (Closure(string): void)|null  $aoBaixar
     *
     * @throws ArquivoNaoEncontrado
     */
    public function coletarDia(CarbonInterface $data, ?Closure $aoBaixar = null): Importacao
    {
        return $this->importarCsv($this->cliente->urlDiaria($data), 'diario', $aoBaixar);
    }

    /**
     * Importa o histórico de um ano.
     *
     * Padrão: arquivo anual do satélite de referência (só MT; se não existir, o do Brasil).
     * Se não houver arquivo anual (ano corrente) ou se $todosSatelites for verdadeiro,
     * usa os arquivos mensais, que trazem todos os satélites.
     *
     * @param  (Closure(string): void)|null  $aoBaixar
     * @return list<Importacao>
     *
     * @throws ArquivoNaoEncontrado
     */
    public function coletarAno(int $ano, bool $todosSatelites = false, ?Closure $aoBaixar = null): array
    {
        if (! $todosSatelites) {
            foreach ([$this->cliente->urlAnualMt($ano), $this->cliente->urlAnualBrasil($ano)] as $url) {
                try {
                    return [$this->importarZip($url, $aoBaixar)];
                } catch (ArquivoNaoEncontrado) {
                    continue;
                }
            }
        }

        $ultimoMes = $ano === (int) now()->year ? (int) now()->month : 12;
        $importacoes = [];

        for ($mes = 1; $mes <= $ultimoMes; $mes++) {
            try {
                $importacoes[] = $this->importarCsv($this->cliente->urlMensal($ano, $mes), 'mensal', $aoBaixar);
            } catch (ArquivoNaoEncontrado) {
                continue;
            }
        }

        if (! $importacoes) {
            throw new ArquivoNaoEncontrado("Nenhum arquivo anual ou mensal do INPE encontrado para {$ano}.");
        }

        return $importacoes;
    }

    private function importarCsv(string $url, string $tipo, ?Closure $aoBaixar): Importacao
    {
        $aoBaixar?->__invoke($url);
        $csv = $this->cliente->baixar($url);

        try {
            return $this->importador->importar($csv, $tipo, basename($url));
        } finally {
            @unlink($csv);
        }
    }

    private function importarZip(string $url, ?Closure $aoBaixar): Importacao
    {
        $aoBaixar?->__invoke($url);
        $zip = $this->cliente->baixar($url);

        try {
            $csv = $this->extrator->extrairCsv($zip);
        } finally {
            @unlink($zip);
        }

        try {
            return $this->importador->importar($csv, 'anual', basename($url));
        } finally {
            @unlink($csv);
        }
    }
}
