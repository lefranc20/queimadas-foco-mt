<?php

namespace App\Services;

/**
 * Classifica o FRP (potência do fogo) em relação ao histórico do próprio satélite.
 * O número cru não é comparável: 50 MW é pouco no GOES-19 e muito no NOAA-20.
 */
class IntensidadeFoco
{
    public const FRACA = 'fraca';

    public const MODERADA = 'moderada';

    public const FORTE = 'forte';

    public const MUITO_FORTE = 'muito forte';

    private const NIVEIS = [self::FRACA, self::MODERADA, self::FORTE, self::MUITO_FORTE];

    /**
     * @param  array<string, array{float|int, float|int, float|int}>  $referencias  satélite => [mediana, 90%, 99%]
     */
    public function __construct(private readonly array $referencias) {}

    public function classificar(?string $satelite, ?float $frp): ?string
    {
        return $this->indice($satelite, $frp) === null ? null : self::NIVEIS[$this->indice($satelite, $frp)];
    }

    /**
     * O foco mais forte de uma lista, comparando cada um com o seu satélite.
     *
     * @param  iterable<object{satelite: string, frp: float|string|null}>  $focos
     * @return array{nivel: string, frp: float, satelite: string}|null
     */
    public function maisForte(iterable $focos): ?array
    {
        $melhor = null;
        $melhorPosicao = -1.0;

        foreach ($focos as $foco) {
            $frp = $foco->frp === null ? null : (float) $foco->frp;
            $indice = $this->indice($foco->satelite, $frp);

            if ($indice === null) {
                continue;
            }

            // dentro da mesma classe, desempata pela proporção em relação à mediana do satélite
            $posicao = $indice * 1000 + $frp / $this->referencias[$foco->satelite][0];

            if ($posicao > $melhorPosicao) {
                $melhorPosicao = $posicao;
                $melhor = ['nivel' => self::NIVEIS[$indice], 'frp' => $frp, 'satelite' => $foco->satelite];
            }
        }

        return $melhor;
    }

    private function indice(?string $satelite, ?float $frp): ?int
    {
        $referencia = $this->referencias[$satelite] ?? null;

        if ($referencia === null || $frp === null) {
            return null;
        }

        return match (true) {
            $frp < $referencia[0] => 0,
            $frp < $referencia[1] => 1,
            $frp < $referencia[2] => 2,
            default => 3,
        };
    }
}
