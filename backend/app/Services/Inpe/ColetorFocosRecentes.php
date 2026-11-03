<?php

namespace App\Services\Inpe;

use App\Models\FocoRecente;
use App\Models\Importacao;
use App\Services\LocalizadorMunicipio;
use Carbon\CarbonImmutable;
use Closure;
use RuntimeException;

/**
 * Coleta os arquivos da pasta 10min/ do INPE, publicados a cada 10 minutos.
 * Cada arquivo traz só lat, lon, satelite e data, do Brasil inteiro: os focos de MT
 * são separados pela malha do IBGE e guardados em uma tabela temporária.
 */
class ColetorFocosRecentes
{
    public const TIPO = 'recente';

    public function __construct(
        private readonly ClienteInpe $cliente,
        private readonly LocalizadorMunicipio $localizador,
        private readonly int $horasGuardadas,
    ) {}

    /**
     * Importa os arquivos das últimas horas que ainda não foram importados e apaga
     * os focos antigos. Olhar várias horas para trás recupera períodos fora do ar.
     *
     * @param  (Closure(string): void)|null  $aoBaixar
     * @return array{arquivos: int, focos: int, novos: int}
     */
    public function coletar(int $horas, ?Closure $aoBaixar = null): array
    {
        $resultado = ['arquivos' => 0, 'focos' => 0, 'novos' => 0];

        foreach ($this->arquivosPendentes($horas) as $horario) {
            $url = $this->cliente->urlDezMinutos($horario);

            try {
                $aoBaixar?->__invoke($url);
                $csv = $this->cliente->baixar($url);
            } catch (ArquivoNaoEncontrado) {
                continue; // ainda não publicado: a próxima execução tenta de novo
            }

            try {
                $importacao = $this->importar($csv, basename($url));
            } finally {
                @unlink($csv);
            }

            $resultado['arquivos']++;
            $resultado['focos'] += $importacao->registros;
            $resultado['novos'] += $importacao->novos;
        }

        $this->apagarAntigos();

        return $resultado;
    }

    /**
     * @return list<CarbonImmutable>
     */
    private function arquivosPendentes(int $horas): array
    {
        $agora = CarbonImmutable::now('UTC');
        $ultimo = $agora->setMinute($agora->minute - $agora->minute % 10)->startOfMinute();

        $horarios = [];
        for ($i = $horas * 6; $i >= 0; $i--) {
            $horario = $ultimo->subMinutes($i * 10);
            $horarios[basename($this->cliente->urlDezMinutos($horario))] = $horario;
        }

        $importados = Importacao::query()
            ->where('tipo', self::TIPO)
            ->where('status', Importacao::CONCLUIDA)
            ->whereIn('arquivo', array_keys($horarios))
            ->pluck('arquivo')
            ->all();

        return array_values(array_diff_key($horarios, array_flip($importados)));
    }

    private function importar(string $caminho, string $arquivo): Importacao
    {
        $linhas = $this->focosDeMatoGrosso($caminho);
        $existentes = FocoRecente::query()->whereIn('chave', array_keys($linhas))->count();

        foreach (array_chunk(array_values($linhas), 500) as $lote) {
            FocoRecente::query()->upsert($lote, ['chave'], ['data_hora_gmt', 'municipio_id']);
        }

        return Importacao::create([
            'tipo' => self::TIPO,
            'arquivo' => $arquivo,
            'status' => Importacao::CONCLUIDA,
            'registros' => count($linhas),
            'novos' => count($linhas) - $existentes,
            'atualizados' => $existentes,
            'concluida_em' => now(),
        ]);
    }

    /**
     * @return array<string, array<string, mixed>> indexado pela chave do foco
     */
    private function focosDeMatoGrosso(string $caminho): array
    {
        $arquivo = @fopen($caminho, 'rb') ?: throw new RuntimeException("Não foi possível abrir {$caminho}");

        try {
            $cabecalho = array_map('trim', fgetcsv($arquivo, escape: '') ?: []);

            if (array_diff(['lat', 'lon', 'satelite', 'data'], $cabecalho)) {
                throw new RuntimeException('Layout inesperado no arquivo de 10 minutos do INPE: '.implode(',', $cabecalho));
            }

            $linhas = [];

            while (($valores = fgetcsv($arquivo, escape: '')) !== false) {
                if (count($valores) !== count($cabecalho)) {
                    continue;
                }

                $linha = array_combine($cabecalho, array_map('trim', $valores));
                $municipio = $this->localizador->localizar((float) $linha['lat'], (float) $linha['lon']);

                if ($municipio === null) {
                    continue;
                }

                $chave = md5(implode('|', [$linha['lat'], $linha['lon'], $linha['data'], $linha['satelite']]));
                $linhas[$chave] = [
                    'chave' => $chave,
                    'lat' => (float) $linha['lat'],
                    'lon' => (float) $linha['lon'],
                    'data_hora_gmt' => $linha['data'],
                    'satelite' => $linha['satelite'],
                    'municipio_id' => $municipio,
                ];
            }

            return $linhas;
        } finally {
            fclose($arquivo);
        }
    }

    private function apagarAntigos(): void
    {
        $limite = now('UTC')->subHours($this->horasGuardadas);

        FocoRecente::query()->where('data_hora_gmt', '<', $limite)->delete();
        Importacao::query()->where('tipo', self::TIPO)->where('created_at', '<', $limite)->delete();
    }
}
