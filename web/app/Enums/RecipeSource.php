<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Origen de una receta: importada de Fuji X Weekly, receta propia creada con
 * los scripts de Python (post_url "mine:<nombre>") o creada en la app.
 */
enum RecipeSource: string implements HasColor, HasLabel
{
    case Fxw = 'fxw';
    case Mine = 'mine';
    case Manual = 'manual';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Fxw => 'gray',
            self::Mine => 'success',
            self::Manual => 'info',
        };
    }

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
