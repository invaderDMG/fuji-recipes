<?php

namespace App\Services;

use App\Models\Recipe;

/**
 * Distancia entre dos recetas (menor = más parecidas; 0 = idénticas) y su
 * desglose por campo.
 */
final class SimilarityResult
{
    /**
     * @param  array<string, float>  $parts  campo => contribución a la distancia
     */
    public function __construct(
        public readonly float $total,
        public readonly array $parts,
        public readonly ?Recipe $recipe = null,
    ) {}

    /**
     * @return list<string>
     */
    public function differingFields(): array
    {
        return array_keys(array_filter($this->parts, fn (float $v) => $v > 0));
    }

    /**
     * Los campos que más pesan en la distancia.
     *
     * @return array<string, float>
     */
    public function topContributors(int $n = 3): array
    {
        $parts = array_filter($this->parts, fn (float $v) => $v > 0);
        arsort($parts);

        return array_slice($parts, 0, $n, true);
    }
}
