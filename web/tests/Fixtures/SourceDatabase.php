<?php

namespace Tests\Fixtures;

use PDO;

/**
 * BD de origen temporal con el esquema de fujixweekly.db (sección 1 del plan).
 */
final class SourceDatabase
{
    private const SCHEMA = <<<'SQL'
        CREATE TABLE posts (url TEXT PRIMARY KEY, title TEXT, published TEXT, image_url TEXT);
        CREATE TABLE recipes (
            id INTEGER PRIMARY KEY,
            post_url TEXT NOT NULL REFERENCES posts(url),
            block_index INTEGER NOT NULL,
            name TEXT, film_simulation TEXT, dynamic_range TEXT, d_range_priority TEXT,
            grain_roughness TEXT, grain_size TEXT, color_chrome_effect TEXT, color_chrome_fx_blue TEXT,
            white_balance TEXT, wb_mode TEXT, wb_kelvin INTEGER, wb_shift_red INTEGER, wb_shift_blue INTEGER,
            tone_curve TEXT, highlight REAL, shadow REAL, color REAL, monochromatic_color TEXT,
            sharpness REAL, high_iso_nr REAL, clarity REAL, smooth_skin TEXT,
            iso TEXT, iso_max INTEGER, exposure_compensation TEXT, raw_json TEXT,
            UNIQUE(post_url, block_index)
        );
        CREATE TABLE recipe_sensors (
            post_url TEXT NOT NULL REFERENCES posts(url), sensor TEXT NOT NULL, index_label TEXT,
            PRIMARY KEY (post_url, sensor, index_label)
        );
        CREATE VIEW v_recipes AS
        SELECT r.*, p.title AS post_title, p.published, p.image_url,
               (SELECT group_concat(DISTINCT s.sensor) FROM recipe_sensors s WHERE s.post_url = r.post_url) AS sensors
        FROM recipes r JOIN posts p ON p.url = r.post_url;
        SQL;

    private PDO $pdo;

    public function __construct(public readonly string $path)
    {
        $this->pdo = new PDO('sqlite:'.$path);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(self::SCHEMA);
    }

    /**
     * Añade las columnas que crea fxw_mine.py al guardar una receta propia.
     */
    public function withAuthorColumns(): self
    {
        $this->pdo->exec('ALTER TABLE posts ADD COLUMN author TEXT');
        $this->pdo->exec('ALTER TABLE posts ADD COLUMN source TEXT');

        return $this;
    }

    /**
     * @param  array<string, mixed>  $recipe
     * @param  array<string, string>  $sensors  sensor => index_label
     */
    public function add(string $url, int $block = 0, array $recipe = [], array $sensors = ['x-trans-v' => 'Test'], array $post = []): self
    {
        $this->insert('posts', ['url' => $url, 'title' => 'Post', 'published' => '2024-05-01T10:00:00+00:00', 'image_url' => null, ...$post], 'INSERT OR IGNORE');

        $recipe = [
            'post_url' => $url,
            'block_index' => $block,
            'name' => 'Kodak Gold',
            'film_simulation' => 'Classic Negative',
            'dynamic_range' => 'DR400',
            'grain_roughness' => 'Weak',
            'grain_size' => 'Small',
            'color_chrome_effect' => 'Strong',
            'color_chrome_fx_blue' => 'Off',
            'white_balance' => 'Daylight, +2 Red & -4 Blue',
            'wb_mode' => 'Daylight',
            'wb_shift_red' => 2,
            'wb_shift_blue' => -4,
            'highlight' => -1.0,
            'shadow' => 0.5,
            'color' => 3.0,
            'sharpness' => -2.0,
            'high_iso_nr' => -4.0,
            'clarity' => -2.0,
            'iso' => 'Auto up to ISO 6400',
            'iso_max' => 6400,
            'exposure_compensation' => '+1/3 (typically)',
            'raw_json' => '{"film simulation": "Classic Negative"}',
            ...$recipe,
        ];
        $this->insert('recipes', $recipe);

        foreach ($sensors as $sensor => $label) {
            $this->insert('recipe_sensors', ['post_url' => $url, 'sensor' => $sensor, 'index_label' => $label], 'INSERT OR IGNORE');
        }

        return $this;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function update(string $url, int $block, array $values): self
    {
        $set = implode(', ', array_map(fn ($c) => "{$c} = :{$c}", array_keys($values)));
        $this->pdo->prepare("UPDATE recipes SET {$set} WHERE post_url = :_url AND block_index = :_block")
            ->execute([...$values, '_url' => $url, '_block' => $block]);

        return $this;
    }

    public function remove(string $url, int $block = 0): self
    {
        $this->pdo->prepare('DELETE FROM recipes WHERE post_url = ? AND block_index = ?')->execute([$url, $block]);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insert(string $table, array $row, string $verb = 'INSERT'): void
    {
        $cols = implode(', ', array_keys($row));
        $params = implode(', ', array_map(fn ($c) => ":{$c}", array_keys($row)));
        $this->pdo->prepare("{$verb} INTO {$table} ({$cols}) VALUES ({$params})")->execute($row);
    }
}
