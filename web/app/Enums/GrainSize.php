<?php

namespace App\Enums;

enum GrainSize: string
{
    case Small = 'Small';
    case Large = 'Large';

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
