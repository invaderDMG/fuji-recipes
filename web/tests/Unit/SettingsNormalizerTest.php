<?php

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\GrainSize;
use App\Import\SettingsNormalizer as N;

it('normalizes effect levels by the first word, like fxw_mine.py', function (?string $raw, ?EffectLevel $value, ?string $review) {
    expect(N::level($raw, EffectLevel::class))->toBe([$value, $review]);
})->with([
    ['Strong', EffectLevel::Strong, null],
    ['weak', EffectLevel::Weak, null],
    ['Off', EffectLevel::Off, null],
    [null, null, null],
    ['', null, null],
    ['Off or N/A', EffectLevel::Off, N::REVIEW_AMBIGUOUS],
    ['Off (X-Trans V); Weak (X-Trans IV)', EffectLevel::Off, N::REVIEW_AMBIGUOUS],
    ['Strong (X-Trans IV), Weak (X-Trans V)', EffectLevel::Strong, N::REVIEW_AMBIGUOUS],
    ['Off/NA', null, N::REVIEW_UNMAPPED],
    ['N/A (X-Trans III) or Off (X-T3/X-T30)', null, N::REVIEW_UNMAPPED],
]);

it('normalizes grain size', function () {
    expect(N::level('Large', GrainSize::class))->toBe([GrainSize::Large, null])
        ->and(N::level('Small - Weak for X-T3/X-T30', GrainSize::class))->toBe([GrainSize::Small, N::REVIEW_AMBIGUOUS]);
});

it('normalizes dynamic range and D Range Priority', function (?string $raw, ?DynamicRange $dr, ?string $priority, ?string $review) {
    expect(N::dynamicRange($raw))->toBe([$dr, $priority, $review]);
})->with([
    ['DR400', DynamicRange::DR400, null, null],
    ['DR100', DynamicRange::DR100, null, null],
    ['DR-Auto', DynamicRange::Auto, null, null],
    [null, null, null, null],
    ['DR-P Strong', DynamicRange::Auto, 'Strong', null],
    ['D-Range Priority Auto', DynamicRange::Auto, 'Auto', null],
    ['D-Range Priority (DR-P) Auto', DynamicRange::Auto, 'Auto', null],
    ['DR400 (DR200 in low contrast situations)', DynamicRange::DR400, null, N::REVIEW_AMBIGUOUS],
    ['N/A', null, null, N::REVIEW_UNMAPPED],
]);

it('normalizes white balance modes', function (?string $raw, ?string $mode) {
    expect(N::whiteBalanceMode($raw))->toBe([$mode, $mode === null && $raw !== null ? N::REVIEW_UNMAPPED : null]);
})->with([
    ['Auto', 'Auto'],
    ['AWB', 'Auto'],
    ['Auto (use a shift on the second exposure)', 'Auto'],
    ['Auto White Priority', 'Auto White Priority'],
    ['Auto White Priority (X100V + X-Pro3: Auto)', 'Auto White Priority'],
    ['Auto Ambience Priority', 'Auto Ambience Priority'],
    ['Daylight', 'Daylight'],
    ['Daylight/Fine', 'Daylight'],
    ['Daylight ("Fine")', 'Daylight'],
    ['Daylight Fluorescent (1)', 'Fluorescent 1'],
    ['Fluorescent 2', 'Fluorescent 2'],
    ['Warm-White Fluorescent (2)', 'Fluorescent 2'],
    ['Fluorescent 3 ("Cool White Fluorescent")', 'Fluorescent 3'],
    ['Shade', 'Shade'],
    ['Cloudy/Shade', 'Shade'],
    ['Incandescent', 'Incandescent'],
    ['Underwater', 'Underwater'],
    ['Custom', 'Custom'],
    ['5200K', 'Kelvin'],
    ['10000K', 'Kelvin'],
    ['Something else', null],
    [null, null],
]);
