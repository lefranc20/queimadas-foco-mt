<?php

namespace App\Services\Inpe;

use RuntimeException;

/**
 * O INPE respondeu 404: o arquivo ainda não foi publicado ou não existe.
 */
class ArquivoNaoEncontrado extends RuntimeException
{
    public static function naUrl(string $url): self
    {
        return new self("Arquivo não encontrado no servidor do INPE: {$url}");
    }
}
