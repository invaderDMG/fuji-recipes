<?php

use App\Enums\FilmSimulation;
use App\Import\FilmSimulationNormalizer;

it('normalizes film simulation aliases', function (?string $raw, ?FilmSimulation $expected) {
    expect(FilmSimulationNormalizer::normalize($raw))->toBe($expected);
})->with([
    // valores canónicos
    ['Classic Chrome', FilmSimulation::ClassicChrome],
    ['Reala Ace', FilmSimulation::RealaAce],
    ['Eterna Bleach Bypass', FilmSimulation::EternaBleachBypass],
    ['Sepia', FilmSimulation::Sepia],
    // mayúsculas, puntos y espacios
    ['PRO Neg. Std', FilmSimulation::ProNegStd],
    ['Pro Neg. Std', FilmSimulation::ProNegStd],
    ['PRO Neg. STD', FilmSimulation::ProNegStd],
    ['pro neg hi', FilmSimulation::ProNegHi],
    ['Nostalgic Neg.', FilmSimulation::NostalgicNeg],
    ['  classic   negative ', FilmSimulation::ClassicNegative],
    // paréntesis finales
    ['Acros (+Y, +R or +G)', FilmSimulation::Acros],
    ['Acros (Acros+Y, Acros+R, Acros+G)', FilmSimulation::Acros],
    ['Acros (including +Ye, +R, or +G)', FilmSimulation::Acros],
    ['Monochrome (Monochrome+Y, Monochrome+R, Monochrome+G)', FilmSimulation::Monochrome],
    // filtros
    ['Acros+R', FilmSimulation::AcrosR],
    ['Acros+Y', FilmSimulation::AcrosYe],
    ['Monochrome+G', FilmSimulation::MonochromeG],
    ['ACROS+Ye FILTER', FilmSimulation::AcrosYe],
    // alias de SIM_ALIASES y del menú de la cámara
    ['Standard', FilmSimulation::Provia],
    ['Provia/STD', FilmSimulation::Provia],
    ['PROVIA/STANDARD', FilmSimulation::Provia],
    ['Velvia/Vivid', FilmSimulation::Velvia],
    ['Classic Neg', FilmSimulation::ClassicNegative],
    ['Nostalgic Negative', FilmSimulation::NostalgicNeg],
    ['PRO Negative Standard', FilmSimulation::ProNegStd],
    ['Eterna/Cinema', FilmSimulation::Eterna],
    ['Bleach Bypass', FilmSimulation::EternaBleachBypass],
    ['B&W', FilmSimulation::Monochrome],
    ['Acros/Acros+R/Acros+G', FilmSimulation::Acros],
    // desconocidos
    ['Any (See Below)', null],
    ['B&W Toning: 0', null],
    ['Kodachrome', null],
    ['', null],
    [null, null],
]);
