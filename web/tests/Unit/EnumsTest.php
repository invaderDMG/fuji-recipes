<?php

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\GrainSize;

it('assigns each film simulation to its family', function (FilmSimulation $sim, string $family) {
    expect($sim->family())->toBe($family);
})->with([
    [FilmSimulation::ProNegHi, 'proneg'],
    [FilmSimulation::ProNegStd, 'proneg'],
    [FilmSimulation::RealaAce, 'proneg'],
    [FilmSimulation::ClassicNegative, 'neg'],
    [FilmSimulation::NostalgicNeg, 'neg'],
    [FilmSimulation::ClassicChrome, 'chrome'],
    [FilmSimulation::Eterna, 'eterna'],
    [FilmSimulation::EternaBleachBypass, 'eterna'],
    [FilmSimulation::Provia, 'slide'],
    [FilmSimulation::Astia, 'slide'],
    [FilmSimulation::Velvia, 'vivid'],
    [FilmSimulation::Acros, 'mono'],
    [FilmSimulation::AcrosYe, 'mono'],
    [FilmSimulation::AcrosR, 'mono'],
    [FilmSimulation::AcrosG, 'mono'],
    [FilmSimulation::Monochrome, 'mono'],
    [FilmSimulation::MonochromeYe, 'mono'],
    [FilmSimulation::MonochromeR, 'mono'],
    [FilmSimulation::MonochromeG, 'mono'],
    [FilmSimulation::Sepia, 'mono'],
]);

it('has the 20 simulations with a camera label', function () {
    expect(FilmSimulation::cases())->toHaveCount(20);

    foreach (FilmSimulation::cases() as $sim) {
        expect($sim->label())->not->toBeEmpty();
    }
});

it('lists the simulations of a family', function () {
    expect(FilmSimulation::ofFamily('neg'))
        ->toBe([FilmSimulation::ClassicNegative, FilmSimulation::NostalgicNeg]);
});

it('maps levels to the ordinals used by the similarity', function () {
    expect(EffectLevel::Off->ordinal())->toBe(0)
        ->and(EffectLevel::Weak->ordinal())->toBe(1)
        ->and(EffectLevel::Strong->ordinal())->toBe(2)
        ->and(GrainSize::Small->ordinal())->toBe(0)
        ->and(GrainSize::Large->ordinal())->toBe(1);
});

it('uses the source spelling for dynamic range values', function () {
    expect(array_column(DynamicRange::cases(), 'value'))
        ->toBe(['DR-Auto', 'DR100', 'DR200', 'DR400']);
});
