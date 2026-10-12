<?php

namespace App\Services\Inpe;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Monta as URLs dos arquivos de focos do INPE e faz o download para disco.
 */
class ClienteInpe
{
    public function __construct(
        private readonly string $urlBase,
        private readonly int $timeout,
    ) {}

    public function urlDiaria(CarbonInterface $data): string
    {
        return "{$this->urlBase}/diario/Brasil/focos_diario_br_{$data->format('Ymd')}.csv";
    }

    public function urlMensal(int $ano, int $mes): string
    {
        return sprintf('%s/mensal/Brasil/focos_mensal_br_%d%02d.csv', $this->urlBase, $ano, $mes);
    }

    /**
     * Arquivo anual só de MT com o satélite de referência.
     */
    public function urlAnualMt(int $ano): string
    {
        return "{$this->urlBase}/anual/EstadosBr_sat_ref/MT/focos_br_mt_ref_{$ano}.zip";
    }

    /**
     * Arquivo anual do Brasil inteiro com o satélite de referência.
     */
    public function urlAnualBrasil(int $ano): string
    {
        return "{$this->urlBase}/anual/Brasil_sat_ref/focos_br_ref_{$ano}.zip";
    }

    /**
     * Baixa o arquivo direto para um arquivo temporário (sem carregar tudo na memória)
     * e devolve o caminho. Quem chama é responsável por apagá-lo.
     *
     * @throws ArquivoNaoEncontrado
     */
    public function baixar(string $url): string
    {
        $destino = tempnam(sys_get_temp_dir(), 'inpe_');

        $resposta = Http::timeout($this->timeout)
            ->retry(3, 2000, $this->deveTentarDeNovo(...), throw: false)
            ->sink($destino)
            ->get($url);

        if ($resposta->successful()) {
            return $destino;
        }

        @unlink($destino);

        if ($resposta->notFound()) {
            throw ArquivoNaoEncontrado::naUrl($url);
        }

        throw new RuntimeException("Falha ao baixar {$url}: HTTP {$resposta->status()}");
    }

    /**
     * Só vale repetir em falha de rede ou erro do servidor; 404 é definitivo.
     */
    private function deveTentarDeNovo(Throwable $erro): bool
    {
        return $erro instanceof ConnectionException
            || ($erro instanceof RequestException && $erro->response->serverError());
    }
}
