<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->tempFile = tempnam(sys_get_temp_dir(), 'fxw');
    $this->sourcePath = $this->tempFile.'.db';

    $pdo = new PDO('sqlite:'.$this->sourcePath);
    $pdo->exec('CREATE TABLE posts (url TEXT PRIMARY KEY, title TEXT)');
    $pdo->exec("INSERT INTO posts VALUES ('https://example.test/a', 'A')");
    $pdo = null;

    config(['database.connections.fxw_source.database' => $this->sourcePath]);
    DB::purge('fxw_source');
});

afterEach(function () {
    DB::purge('fxw_source');
    @unlink($this->sourcePath);
    @unlink($this->tempFile);
});

it('reads the source database', function () {
    expect(DB::connection('fxw_source')->table('posts')->count())->toBe(1);
});

it('refuses to write to the source database', function () {
    $hash = hash_file('sha256', $this->sourcePath);

    expect(fn () => DB::connection('fxw_source')->table('posts')->insert(['url' => 'x', 'title' => 'X']))
        ->toThrow(QueryException::class, 'readonly');

    expect(hash_file('sha256', $this->sourcePath))->toBe($hash);
});

it('reads fuji settings from config', function () {
    expect(config('fuji.default_sensor'))->toBe('x-trans-v');
});
