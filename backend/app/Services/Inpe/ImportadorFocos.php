<?php

namespace App\Services\Inpe;

use App\Models\Foco;
use App\Models\Importacao;
use App\Models\Municipio;
use Illuminate\Support\Str;
use Throwable;

/**
 * Grava no banco os focos de um CSV do INPE, em lotes, com upsert pelo id do foco.
 * Rodar a mesma importação duas vezes atualiza os registros em vez de duplicá-los.
 */
class ImportadorFocos
{
    private const TAMANHO_LOTE = 500;

    /** @var array<string, int> nome normalizado => código IBGE */
    private array $municipiosPorNome = [];

    /** @var array<int, true> */
    private array $municipiosConhecidos = [];

    public function __construct(private readonly LeitorCsvFocos $leitor) {}

    public function importar(string $caminho, string $tipo, string $arquivo): Importacao
    {
        $importacao = Importacao::create([
            'tipo' => $tipo,
            'arquivo' => $arquivo,
            'status' => Importacao::EXECUTANDO,
            'registros' => 0,
            'novos' => 0,
            'atualizados' => 0,
        ]);

        $this->municipiosPorNome = Municipio::mapaPorNome();
        $this->municipiosConhecidos = array_fill_keys(array_values($this->municipiosPorNome), true);

        try {
            $lote = [];

            foreach ($this->leitor->ler($caminho) as $foco) {
                // indexar pelo id remove focos repetidos dentro do mesmo lote
                $lote[$foco['id']] = $this->resolverMunicipio($foco);

                if (count($lote) >= self::TAMANHO_LOTE) {
                    $this->gravar($lote, $importacao);
                    $lote = [];
                }
            }

            if ($lote) {
                $this->gravar($lote, $importacao);
            }

            $importacao->update([
                'status' => Importacao::CONCLUIDA,
                'concluida_em' => now(),
            ]);
        } catch (Throwable $erro) {
            $importacao->update([
                'status' => Importacao::FALHOU,
                'mensagem' => Str::limit($erro->getMessage(), 1000),
            ]);

            throw $erro;
        }

        return $importacao;
    }

    /**
     * @param  array<string, mixed>  $foco
     * @return array<string, mixed>
     */
    private function resolverMunicipio(array $foco): array
    {
        $nome = $foco['municipio'];
        unset($foco['municipio']);

        $foco['municipio_id'] ??= $this->municipiosPorNome[Municipio::normalizarNome($nome)] ?? null;

        if ($foco['municipio_id'] && ! isset($this->municipiosConhecidos[$foco['municipio_id']])) {
            // município que não estava na carga inicial: cadastra com o nome que veio do INPE
            Municipio::create(['id' => $foco['municipio_id'], 'nome' => Str::title(Str::lower($nome))]);
            $this->municipiosConhecidos[$foco['municipio_id']] = true;
        }

        return $foco;
    }

    /**
     * @param  array<string, array<string, mixed>>  $lote
     */
    private function gravar(array $lote, Importacao $importacao): void
    {
        $existentes = Foco::query()->whereIn('id', array_keys($lote))->count();

        $linhas = array_values($lote);

        // Só atualiza as colunas que vieram no arquivo: o layout anual não traz os dados
        // meteorológicos e não pode apagar o que a importação diária já gravou.
        $colunasAtualizadas = array_values(array_diff(array_keys($linhas[0]), ['id']));

        Foco::query()->upsert($linhas, ['id'], $colunasAtualizadas);

        $importacao->registros += count($linhas);
        $importacao->novos += count($linhas) - $existentes;
        $importacao->atualizados += $existentes;
    }
}
