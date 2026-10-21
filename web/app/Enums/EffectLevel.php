<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Nivel de efecto: rugosidad del grano, Color Chrome Effect, FX Blue y Smooth Skin.
 */
enum EffectLevel: string implements HasLabel
{
    case Off = 'Off';
    case Weak = 'Weak';
    case Strong = 'Strong';

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
            self::Off => 0,
            self::Weak => 1,
            self::Strong => 2,
        };
    }
}
