<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum DynamicRange: string implements HasLabel
{
    case Auto = 'DR-Auto';
    case DR100 = 'DR100';
    case DR200 = 'DR200';
    case DR400 = 'DR400';

    public function getLabel(): string
    {
        return $this->label();
    }

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'AUTO',
            default => $this->value,
        };
    }
}
