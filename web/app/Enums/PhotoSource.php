<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PhotoSource: string implements HasColor, HasLabel
{
    case Sample = 'sample';
    case Mine = 'mine';

    public function getLabel(): string
    {
        return match ($this) {
            self::Sample => 'Muestra (Fuji X Weekly)',
            self::Mine => 'Mía',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sample => 'gray',
            self::Mine => 'success',
        };
    }
}
