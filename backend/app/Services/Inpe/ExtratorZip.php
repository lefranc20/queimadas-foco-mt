<?php

namespace App\Services\Inpe;

use RuntimeException;

/**
 * Extrai o CSV de dentro dos .zip anuais do INPE usando apenas zlib, sem depender
 * da extensão ext-zip (que nem sempre vem habilitada, como no PHP padrão do Windows).
 *
 * Suporta entradas sem compressão (stored) e com deflate, que é o que o INPE publica.
 * Não suporta ZIP64 (arquivos acima de 4 GB), o que não acontece com essas bases.
 */
class ExtratorZip
{
    private const ASSINATURA_FIM_DIRETORIO = "PK\x05\x06";

    private const ASSINATURA_DIRETORIO = "PK\x01\x02";

    private const ASSINATURA_ARQUIVO = "PK\x03\x04";

    private const TAMANHO_BLOCO = 1024 * 1024;

    /**
     * Extrai o primeiro .csv encontrado no zip e devolve o caminho do arquivo temporário criado.
     */
    public function extrairCsv(string $zip): string
    {
        $origem = @fopen($zip, 'rb') ?: throw new RuntimeException("Não foi possível abrir {$zip}");

        try {
            $entrada = $this->localizarCsv($origem);
            $destino = tempnam(sys_get_temp_dir(), 'inpe_csv_');
            $this->copiarConteudo($origem, $entrada, $destino);

            return $destino;
        } finally {
            fclose($origem);
        }
    }

    /**
     * Lê o diretório central e encontra a entrada do CSV.
     *
     * @param  resource  $arquivo
     * @return array{metodo: int, tamanho: int, deslocamento: int}
     */
    private function localizarCsv($arquivo): array
    {
        $tamanhoArquivo = fstat($arquivo)['size'];
        $leitura = min($tamanhoArquivo, 22 + 65535); // registro final + comentário de tamanho máximo
        fseek($arquivo, $tamanhoArquivo - $leitura);
        $cauda = (string) fread($arquivo, $leitura);

        $posicao = strrpos($cauda, self::ASSINATURA_FIM_DIRETORIO);
        if ($posicao === false) {
            throw new RuntimeException('Arquivo zip inválido: fim do diretório central não encontrado.');
        }

        $fim = unpack('vdisco/vdiscoDiretorio/ventradasDisco/ventradas/Vtamanho/Vdeslocamento', $cauda, $posicao + 4);
        fseek($arquivo, $fim['deslocamento']);
        $diretorio = (string) fread($arquivo, $fim['tamanho']);

        $cursor = 0;
        for ($i = 0; $i < $fim['entradas']; $i++) {
            if (substr($diretorio, $cursor, 4) !== self::ASSINATURA_DIRETORIO) {
                throw new RuntimeException('Arquivo zip inválido: diretório central corrompido.');
            }

            $cabecalho = unpack(
                'vversao/vversaoNecessaria/vflags/vmetodo/vhora/vdata/Vcrc/VtamanhoCompactado/VtamanhoOriginal/'
                .'vtamanhoNome/vtamanhoExtra/vtamanhoComentario/vdiscoInicio/vatributosInternos/VatributosExternos/Vdeslocamento',
                $diretorio,
                $cursor + 4,
            );
            $nome = substr($diretorio, $cursor + 46, $cabecalho['tamanhoNome']);
            $cursor += 46 + $cabecalho['tamanhoNome'] + $cabecalho['tamanhoExtra'] + $cabecalho['tamanhoComentario'];

            if (! str_ends_with(strtolower($nome), '.csv')) {
                continue;
            }

            if ($cabecalho['tamanhoCompactado'] === 0xFFFFFFFF) {
                throw new RuntimeException('Arquivos ZIP64 não são suportados.');
            }

            return [
                'metodo' => $cabecalho['metodo'],
                'tamanho' => $cabecalho['tamanhoCompactado'],
                'deslocamento' => $cabecalho['deslocamento'],
            ];
        }

        throw new RuntimeException('Nenhum arquivo .csv encontrado dentro do zip.');
    }

    /**
     * @param  resource  $arquivo
     * @param  array{metodo: int, tamanho: int, deslocamento: int}  $entrada
     */
    private function copiarConteudo($arquivo, array $entrada, string $destino): void
    {
        fseek($arquivo, $entrada['deslocamento']);
        $cabecalhoLocal = (string) fread($arquivo, 30);

        if (substr($cabecalhoLocal, 0, 4) !== self::ASSINATURA_ARQUIVO) {
            throw new RuntimeException('Arquivo zip inválido: cabeçalho local não encontrado.');
        }

        $tamanhos = unpack('vnome/vextra', $cabecalhoLocal, 26);
        fseek($arquivo, $entrada['deslocamento'] + 30 + $tamanhos['nome'] + $tamanhos['extra']);

        $inflador = match ($entrada['metodo']) {
            0 => null,
            8 => inflate_init(ZLIB_ENCODING_RAW),
            default => throw new RuntimeException("Método de compressão {$entrada['metodo']} não suportado."),
        };

        $saida = fopen($destino, 'wb');
        $restante = $entrada['tamanho'];

        while ($restante > 0) {
            $bloco = fread($arquivo, min(self::TAMANHO_BLOCO, $restante));
            if ($bloco === '' || $bloco === false) {
                break;
            }
            $restante -= strlen($bloco);

            fwrite($saida, $inflador ? inflate_add($inflador, $bloco, ZLIB_SYNC_FLUSH) : $bloco);
        }

        if ($inflador) {
            fwrite($saida, inflate_add($inflador, '', ZLIB_FINISH));
        }

        fclose($saida);
    }
}
