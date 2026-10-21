<?php

namespace App\Import;

use App\Enums\RecipeSource;

/**
 * Una receta del origen ya normalizada, lista para comparar con la BD de Laravel.
 */
final class MappedRecipe
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, string>  $issues  campo => motivo de revisión
     * @param  array<string, string|null>  $sensors  slug => index_label
     * @param  array<string, array{reason: string, raw: ?string, suggestion: ?string}>  $reviewIssues
     */
    public function __construct(
        public readonly RecipeSource $source,
        public readonly string $sourceUrl,
        public readonly int $blockIndex,
        public readonly array $attributes,
        public readonly array $issues,
        public readonly array $sensors,
        public readonly array $reviewIssues = [],
    ) {}

    public function key(): string
    {
        return "{$this->sourceUrl}#{$this->blockIndex}";
    }
}
