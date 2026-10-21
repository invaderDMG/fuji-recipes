<?php

namespace App\Enums;

/**
 * Origen de una receta: importada de Fuji X Weekly, receta propia creada con
 * los scripts de Python (post_url "mine:<nombre>") o creada en la app.
 */
enum RecipeSource: string
{
    case Fxw = 'fxw';
    case Mine = 'mine';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Fxw => 'Fuji X Weekly',
            self::Mine => 'Propia (scripts)',
            self::Manual => 'Manual',
        };
    }

    public function isImported(): bool
    {
        return $this !== self::Manual;
    }
}
