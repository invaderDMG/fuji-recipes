<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Usa una conexión aparte para no interferir con la transacción de RefreshDatabase.
beforeEach(function () {
    config(['database.connections.migrations_test' => [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => true,
    ]]);
});

afterEach(function () {
    DB::purge('migrations_test');
});

it('migrates from scratch and rolls back completely', function () {
    $tables = ['sensors', 'recipes', 'recipe_sensor', 'tags', 'recipe_tag', 'collections', 'collection_recipe'];
    $schema = Schema::connection('migrations_test');

    expect(Artisan::call('migrate', ['--database' => 'migrations_test']))->toBe(0);
    foreach ($tables as $table) {
        expect($schema->hasTable($table))->toBeTrue("Falta la tabla {$table}");
    }

    expect(Artisan::call('migrate:reset', ['--database' => 'migrations_test']))->toBe(0);
    foreach ($tables as $table) {
        expect($schema->hasTable($table))->toBeFalse("Sobra la tabla {$table}");
    }

    expect(Artisan::call('migrate', ['--database' => 'migrations_test']))->toBe(0);
});
