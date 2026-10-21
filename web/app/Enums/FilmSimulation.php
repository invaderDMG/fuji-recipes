<?php

namespace App\Enums;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Simulaciones de película. El valor es el nombre canónico que usan los
 * scripts de Python (SIMS en fxw_import.py); label() es el texto del menú
 * de la cámara.
 */
enum FilmSimulation: string implements HasColor, HasLabel
{
    case Provia = 'Provia';
    case Velvia = 'Velvia';
    case Astia = 'Astia';
    case ClassicChrome = 'Classic Chrome';
    case RealaAce = 'Reala Ace';
    case ProNegHi = 'PRO Neg. Hi';
    case ProNegStd = 'PRO Neg. Std';
    case ClassicNegative = 'Classic Negative';
    case NostalgicNeg = 'Nostalgic Neg.';
    case Eterna = 'Eterna';
    case EternaBleachBypass = 'Eterna Bleach Bypass';
    case Acros = 'Acros';
    case AcrosYe = 'Acros+Ye';
    case AcrosR = 'Acros+R';
    case AcrosG = 'Acros+G';
    case Monochrome = 'Monochrome';
    case MonochromeYe = 'Monochrome+Ye';
    case MonochromeR = 'Monochrome+R';
    case MonochromeG = 'Monochrome+G';
    case Sepia = 'Sepia';

    /**
     * Familia para la similitud (SIM_FAMILY en fxw_mine.py).
     */
    public function family(): string
    {
        return match ($this) {
            self::ProNegHi, self::ProNegStd, self::RealaAce => 'proneg',
            self::ClassicNegative, self::NostalgicNeg => 'neg',
            self::ClassicChrome => 'chrome',
            self::Eterna, self::EternaBleachBypass => 'eterna',
            self::Provia, self::Astia => 'slide',
            self::Velvia => 'vivid',
            self::Acros, self::AcrosYe, self::AcrosR, self::AcrosG,
            self::Monochrome, self::MonochromeYe, self::MonochromeR, self::MonochromeG,
            self::Sepia => 'mono',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    /**
     * Color del badge según la familia.
     *
     * @return array<int|string, string>
     */
    public function getColor(): array
    {
        return self::familyColor($this->family());
    }

    public function label(): string
    {
        return match ($this) {
            self::Provia => 'PROVIA/STANDARD',
            self::Velvia => 'Velvia/VIVID',
            self::Astia => 'ASTIA/SOFT',
            self::ClassicChrome => 'CLASSIC CHROME',
            self::RealaAce => 'REALA ACE',
            self::ProNegHi => 'PRO Neg. Hi',
            self::ProNegStd => 'PRO Neg. Std',
            self::ClassicNegative => 'CLASSIC Neg.',
            self::NostalgicNeg => 'NOSTALGIC Neg.',
            self::Eterna => 'ETERNA/CINEMA',
            self::EternaBleachBypass => 'ETERNA BLEACH BYPASS',
            self::Acros => 'ACROS',
            self::AcrosYe => 'ACROS+Ye FILTER',
            self::AcrosR => 'ACROS+R FILTER',
            self::AcrosG => 'ACROS+G FILTER',
            self::Monochrome => 'MONOCHROME',
            self::MonochromeYe => 'MONOCHROME+Ye FILTER',
            self::MonochromeR => 'MONOCHROME+R FILTER',
            self::MonochromeG => 'MONOCHROME+G FILTER',
            self::Sepia => 'SEPIA',
        };
    }

    public const FAMILIES = [
        'proneg' => 'PRO Neg / Reala',
        'neg' => 'Negativo',
        'chrome' => 'Classic Chrome',
        'eterna' => 'Eterna',
        'slide' => 'Diapositiva',
        'vivid' => 'Velvia',
        'mono' => 'Monocromo',
    ];

    /**
     * Tinta de cada familia, como el color de una caja de película.
     * Las mismas que --fuji-ink en public/css/fuji.css (allí también las de modo oscuro).
     */
    public const FAMILY_INK = [
        'proneg' => '#76688f',
        'neg' => '#b8711f',
        'chrome' => '#7a7548',
        'eterna' => '#3c7559',
        'slide' => '#2f65a8',
        'vivid' => '#ae2f66',
        'mono' => '#555b58',
    ];

    /**
     * @return array<int|string, string>
     */
    public static function familyColor(string $family): array
    {
        return Color::hex(self::FAMILY_INK[$family] ?? self::FAMILY_INK['mono']);
    }

    /**
     * @return list<self>
     */
    public static function ofFamily(string $family): array
    {
        return array_values(array_filter(self::cases(), fn (self $sim) => $sim->family() === $family));
    }
}
