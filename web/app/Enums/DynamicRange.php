<?php

namespace App\Enums;

enum DynamicRange: string
{
    case Auto = 'DR-Auto';
    case DR100 = 'DR100';
    case DR200 = 'DR200';
    case DR400 = 'DR400';

    public function label(): string
    {
        return match ($this) {
            self::Auto => 'AUTO',
            default => $this->value,
        };
    }
}
