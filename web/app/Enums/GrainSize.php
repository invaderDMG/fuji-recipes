<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum GrainSize: string implements HasLabel
{
    case Small = 'Small';
    case Large = 'Large';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return $this->value;
    }

    /**
     * Valor ordinal para la similitud (LEVEL en fxw_mine.py).
     */
    public function ordinal(): int
    {
        return match ($this) {
            self::Small => 0,
            self::Large => 1,
        };
    }
}
