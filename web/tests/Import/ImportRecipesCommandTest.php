<?php

use App\Enums\DynamicRange;
use App\Enums\EffectLevel;
use App\Enums\FilmSimulation;
use App\Enums\RecipeSource;
use App\Import\RecipeImporter;
use App\Import\SourceReader;
use App\Models\Recipe;
use App\Models\Sensor;
use Database\Seeders\SensorSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Fixtures\SourceDatabase;

const POST_A = 'https://fujixweekly.com/2024/05/01/kodak-gold/';
const POST_B = 'https://fujixweekly.com/2024/06/01/two-recipes/';

beforeEach(function () {
    $this->dir = sys_get_temp_dir().'/fxw-import-'.uniqid();
    File::ensureDirectoryExists($this->dir);
    touch("{$this->dir}/app.sqlite");

    config([
        'database.connections.sqlite.database' => "{$this->dir}/app.sqlite",
        'database.connections.fxw_source.database' => "{$this->dir}/source.db",
        'fuji.source_db' => "{$this->dir}/source.db",
        'fuji.backups.path' => "{$this->dir}/backups",
    ]);
    DB::purge('sqlite');
    DB::purge('fxw_source');

    Artisan::call('migrate');
    $this->seed(SensorSeeder::class);

    $this->source = (new SourceDatabase("{$this->dir}/source.db"))
        ->add(POST_A, 0, [], ['x-trans-v' => 'Kodak Gold 200', 'x-trans-iv' => 'Kodak Gold'])
        ->add(POST_B, 0, ['name' => 'Recipe One', 'film_simulation' => 'Reala Ace'])
        ->add(POST_B, 1, ['name' => 'Recipe Two', 'film_simulation' => 'Eterna']);
});

afterEach(function () {
    DB::purge('sqlite');
    DB::purge('fxw_source');
    File::deleteDirectory($this->dir);
});

function backups(string $dir): array
{
    return glob("{$dir}/backups/database-*.sqlite") ?: [];
}

it('only shows the diff by default and with --dry-run', function (array $options) {
    $this->artisan('recipes:import', $options)
        ->expectsOutputToContain('Simulación: no se ha escrito nada')
        ->expectsTable(['Resumen', 'Recetas'], [
            ['Creadas', 3],
            ['Actualizadas', 0],
            ['Sin cambios', 0],
            ['Campos protegidos por bloqueo', 0],
            ['Desaparecidas del origen', 0],
            ['Reaparecidas', 0],
            ['Pendientes de revisión (needs_review)', 0],
        ])
        ->assertSuccessful();

    expect(Recipe::count())->toBe(0)
        ->and(backups($this->dir))->toBe([]);
})->with([[[]], [['--dry-run' => true]]]);

it('imports recipes with --force, normalizing and syncing sensors', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    expect(Recipe::count())->toBe(3);

    $gold = Recipe::where('source_url', POST_A)->firstOrFail();
    expect($gold->source)->toBe(RecipeSource::Fxw)
        ->and($gold->film_simulation)->toBe(FilmSimulation::ClassicNegative)
        ->and($gold->dynamic_range)->toBe(DynamicRange::DR400)
        ->and($gold->grain_roughness)->toBe(EffectLevel::Weak)
        ->and($gold->wb_mode)->toBe('Daylight')
        ->and($gold->highlight)->toBe('-1.0')
        ->and($gold->published_at->toDateString())->toBe('2024-05-01')
        ->and($gold->imported_at)->not->toBeNull()
        ->and($gold->raw_json)->toBe(['film simulation' => 'Classic Negative'])
        ->and($gold->sensors->pluck('pivot.index_label', 'slug')->sortKeys()->all())
        ->toBe(['x-trans-iv' => 'Kodak Gold', 'x-trans-v' => 'Kodak Gold 200']);
});

it('creates a backup before writing and keeps the 10 most recent', function () {
    File::ensureDirectoryExists("{$this->dir}/backups");
    foreach (range(1, 12) as $i) {
        touch(sprintf('%s/backups/database-20200101-0000%02d.sqlite', $this->dir, $i));
    }

    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    $files = backups($this->dir);
    expect($files)->toHaveCount(10)
        ->and(basename(end($files)))->toStartWith('database-'.now()->format('Ymd'))
        ->and(filesize(end($files)))->toBeGreaterThan(0);
});

it('is idempotent', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();
    $updatedAt = Recipe::pluck('updated_at', 'id');

    $this->travel(1)->minute();

    $this->artisan('recipes:import', ['--force' => true])
        ->expectsTable(['Resumen', 'Recetas'], [
            ['Creadas', 0],
            ['Actualizadas', 0],
            ['Sin cambios', 3],
            ['Campos protegidos por bloqueo', 0],
            ['Desaparecidas del origen', 0],
            ['Reaparecidas', 0],
            ['Pendientes de revisión (needs_review)', 0],
        ])
        ->assertSuccessful();

    expect(Recipe::pluck('updated_at', 'id')->map->toDateTimeString()->all())
        ->toBe($updatedAt->map->toDateTimeString()->all());
});

it('reports field changes from the source', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();
    $this->source->update(POST_A, 0, ['highlight' => 1.5, 'dynamic_range' => 'DR200']);

    $this->artisan('recipes:import')
        ->expectsOutputToContain('highlight: -1.0 → 1.5')
        ->expectsOutputToContain('dynamic_range: DR400 → DR200')
        ->assertSuccessful();

    expect(Recipe::where('source_url', POST_A)->value('highlight'))->toEqual(-1.0);
});

it('does not overwrite locked fields', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();
    Recipe::where('source_url', POST_A)->firstOrFail()
        ->update(['highlight' => 2, 'locked_fields' => ['highlight']]);

    $this->source->update(POST_A, 0, ['highlight' => 1.5, 'shadow' => -1.0, 'raw_json' => '{"highlight": "+1.5"}']);

    $this->artisan('recipes:import', ['--force' => true])
        ->expectsOutputToContain('highlight: se mantiene 2.0 (origen: 1.5)')
        ->assertSuccessful();

    $gold = Recipe::where('source_url', POST_A)->firstOrFail();
    expect($gold->highlight)->toBe('2.0')
        ->and($gold->shadow)->toBe('-1.0')
        ->and($gold->raw_json)->toBe(['highlight' => '+1.5'])
        ->and($gold->locked_fields)->toBe(['highlight']);
});

it('marks recipes that disappear from the source and clears the mark when they reappear', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    $this->source->remove(POST_B, 1);
    $this->artisan('recipes:import', ['--force' => true])
        ->expectsOutputToContain('- Recipe Two')
        ->assertSuccessful();

    $two = Recipe::where('source_url', POST_B)->where('block_index', 1)->firstOrFail();
    expect(Recipe::count())->toBe(3)
        ->and($two->missing_from_source_at)->not->toBeNull();

    $this->source->add(POST_B, 1, ['name' => 'Recipe Two', 'film_simulation' => 'Eterna']);
    $this->artisan('recipes:import', ['--force' => true])
        ->expectsOutputToContain('↺ Recipe Two')
        ->assertSuccessful();

    expect($two->fresh()->missing_from_source_at)->toBeNull();
});

it('flags unmapped simulations and groups them in the summary', function () {
    $this->source
        ->add('https://fujixweekly.com/any/', 0, ['film_simulation' => 'Any (See Below)'])
        ->add('https://fujixweekly.com/any2/', 0, ['film_simulation' => 'Any (See Below)'])
        ->add('https://fujixweekly.com/none/', 0, ['film_simulation' => null])
        ->add('https://fujixweekly.com/acros/', 0, ['film_simulation' => 'Acros (+Y, +R or +G)']);

    $this->artisan('recipes:import', ['--force' => true])
        ->expectsTable(['Simulación sin mapear', 'Recetas'], [['Any (See Below)', 2]])
        ->expectsOutputToContain('film_simulation: vacío')
        ->assertSuccessful();

    $any = Recipe::where('source_url', 'https://fujixweekly.com/any/')->firstOrFail();
    expect($any->film_simulation)->toBeNull()
        ->and($any->film_simulation_raw)->toBe('Any (See Below)')
        ->and($any->needs_review)->toBeTrue()
        ->and(Recipe::where('source_url', 'https://fujixweekly.com/acros/')->value('film_simulation'))->toBe(FilmSimulation::Acros)
        ->and(Recipe::where('needs_review', true)->count())->toBe(3);
});

it('flags out-of-range and ambiguous settings without rejecting the recipe', function () {
    $this->source->add('https://fujixweekly.com/x-trans-ii/', 0, [
        'highlight' => 3.3,
        'color_chrome_fx_blue' => 'Strong (X-Trans IV), Weak (X-Trans V)',
    ]);

    $this->artisan('recipes:import', ['--force' => true])
        ->expectsOutputToContain('highlight: fuera de rango')
        ->expectsOutputToContain('color_chrome_fx_blue: ambiguo')
        ->assertSuccessful();

    $old = Recipe::where('source_url', 'https://fujixweekly.com/x-trans-ii/')->firstOrFail();
    expect($old->needs_review)->toBeTrue()
        ->and($old->color_chrome_fx_blue)->toBe(EffectLevel::Strong);
});

it('does not flag review issues on locked fields', function () {
    $this->source->add('https://fujixweekly.com/any/', 0, ['film_simulation' => 'Any (See Below)']);
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    $any = Recipe::where('source_url', 'https://fujixweekly.com/any/')->firstOrFail();
    $any->update(['film_simulation' => FilmSimulation::Provia, 'locked_fields' => ['film_simulation']]);

    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    expect($any->fresh()->needs_review)->toBeFalse()
        ->and($any->fresh()->film_simulation)->toBe(FilmSimulation::Provia);
});

it('imports own recipes with author and only the requested origin', function () {
    $this->source->withAuthorColumns()
        ->add('mine:Mi Gold', 0, ['name' => 'Mi Gold'], ['x-trans-v' => 'mine'], ['author' => 'Yo']);
    Recipe::factory()->manual()->create();

    $this->artisan('recipes:import', ['--force' => true, '--only' => 'mine'])->assertSuccessful();

    $mine = Recipe::where('source_url', 'mine:Mi Gold')->firstOrFail();
    expect(Recipe::where('source', 'fxw')->count())->toBe(0)
        ->and($mine->source)->toBe(RecipeSource::Mine)
        ->and($mine->author)->toBe('Yo')
        ->and(Recipe::whereNotNull('missing_from_source_at')->count())->toBe(0);
});

it('creates unknown sensors', function () {
    $this->source->add('https://fujixweekly.com/new/', 0, [], ['x-trans-vi' => 'Future']);

    $this->artisan('recipes:import', ['--force' => true])
        ->expectsOutputToContain('Sensores nuevos (se crearán al importar): x-trans-vi')
        ->assertSuccessful();

    expect(Sensor::where('slug', 'x-trans-vi')->exists())->toBeTrue();
});

it('never modifies the source database file', function () {
    $hash = hash_file('sha256', $this->source->path);

    $this->artisan('recipes:import')->assertSuccessful();
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();

    expect(hash_file('sha256', $this->source->path))->toBe($hash);
});

it('fails with a clear message', function (array $options, ?string $sourceDb, string $message) {
    config(['fuji.source_db' => $sourceDb]);

    $this->artisan('recipes:import', $options)
        ->expectsOutputToContain($message)
        ->assertFailed();
})->with([
    'sin configurar' => [[], null, 'FXW_SOURCE_DB no está configurado'],
    'no existe' => [[], '/nonexistent/fujixweekly.db', 'No existe la BD de origen'],
    'origen inválido' => [['--only' => 'otro'], 'unused', '--only debe ser fxw o mine'],
    'opciones incompatibles' => [['--dry-run' => true, '--force' => true], 'unused', 'incompatibles'],
]);

it('restores a recipe from the source and clears its locks', function () {
    $this->artisan('recipes:import', ['--force' => true])->assertSuccessful();
    $gold = Recipe::where('source_url', POST_A)->firstOrFail();
    $gold->update(['highlight' => 3, 'name' => 'Mi versión', 'locked_fields' => ['highlight', 'name']]);

    (new RecipeImporter(SourceReader::fromConfig()))->restore($gold);

    $gold->refresh();
    expect($gold->locked_fields)->toBe([])
        ->and($gold->highlight)->toBe('-1.0')
        ->and($gold->name)->toBe('Kodak Gold')
        ->and($gold->sensors)->toHaveCount(2);
});

it('refuses to restore manual recipes or recipes missing from the source', function () {
    $importer = new RecipeImporter(SourceReader::fromConfig());

    expect(fn () => $importer->restore(Recipe::factory()->manual()->create()))
        ->toThrow(DomainException::class, 'Solo se pueden restaurar recetas importadas')
        ->and(fn () => $importer->restore(Recipe::factory()->create()))
        ->toThrow(DomainException::class, 'ya no está en el origen');
});
