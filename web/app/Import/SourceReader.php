<?php

namespace App\Import;

use App\Enums\RecipeSource;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Lee fujixweekly.db a través de la conexión de solo lectura "fxw_source".
 */
final class SourceReader
{
    private ?bool $hasAuthor = null;

    public function __construct(private readonly ConnectionInterface $db) {}

    public static function fromConfig(): self
    {
        $path = config('fuji.source_db');

        if (! $path) {
            throw new RuntimeException('FXW_SOURCE_DB no está configurado en .env.');
        }

        if (! is_file($path)) {
            throw new RuntimeException("No existe la BD de origen: {$path}");
        }

        $db = DB::connection('fxw_source');

        foreach (['posts', 'recipes', 'recipe_sensors', 'v_recipes'] as $table) {
            if (! $db->table('sqlite_master')->where('name', $table)->exists()) {
                throw new RuntimeException("La BD de origen no tiene la tabla o vista {$table}: {$path}");
            }
        }

        return new self($db);
    }

    /**
     * Filas de v_recipes con el autor del post (si la columna existe).
     *
     * @return list<object>
     */
    public function recipes(?RecipeSource $only = null): array
    {
        $query = $this->db->table('v_recipes as r')
            ->select('r.*')
            ->orderBy('r.post_url')
            ->orderBy('r.block_index');

        if ($this->hasAuthor()) {
            $query->leftJoin('posts as p', 'p.url', '=', 'r.post_url')->addSelect('p.author');
        }

        match ($only) {
            RecipeSource::Mine => $query->where('r.post_url', 'like', 'mine:%'),
            RecipeSource::Fxw => $query->where('r.post_url', 'not like', 'mine:%'),
            default => null,
        };

        return $query->get()->all();
    }

    public function find(string $postUrl, int $blockIndex): ?object
    {
        $query = $this->db->table('v_recipes as r')
            ->select('r.*')
            ->where('r.post_url', $postUrl)
            ->where('r.block_index', $blockIndex);

        if ($this->hasAuthor()) {
            $query->leftJoin('posts as p', 'p.url', '=', 'r.post_url')->addSelect('p.author');
        }

        return $query->first();
    }

    /**
     * Sensores de cada post con su título en el índice. Si un post aparece
     * varias veces en el mismo índice, los títulos se unen con " · ".
     *
     * @return array<string, array<string, string|null>> post_url => [sensor => index_label]
     */
    public function sensors(?string $postUrl = null): array
    {
        $rows = $this->db->table('recipe_sensors')
            ->when($postUrl, fn ($q) => $q->where('post_url', $postUrl))
            ->orderBy('post_url')->orderBy('sensor')->orderBy('index_label')
            ->get();

        $sensors = [];

        foreach ($rows as $row) {
            $current = $sensors[$row->post_url][$row->sensor] ?? null;
            $label = SettingsNormalizer::clean($row->index_label);
            $sensors[$row->post_url][$row->sensor] = $current === null ? $label : ($label === null ? $current : "{$current} · {$label}");
        }

        return $sensors;
    }

    /**
     * Fotos de muestra de cada post, en orden. La tabla post_photos solo existe
     * si fxw_scraper.py se ha ejecutado con la versión que las extrae.
     *
     * @return array<string, list<array{url: string, caption: ?string, width: ?int, height: ?int, position: int}>>
     */
    public function photos(?string $postUrl = null): array
    {
        if (! $this->db->table('sqlite_master')->where('name', 'post_photos')->exists()) {
            return [];
        }

        $photos = [];
        $rows = $this->db->table('post_photos')
            ->when($postUrl, fn ($q) => $q->where('post_url', $postUrl))
            ->orderBy('post_url')->orderBy('position')
            ->get();

        foreach ($rows as $row) {
            $photos[$row->post_url][] = [
                'url' => $row->url,
                'caption' => SettingsNormalizer::clean($row->caption),
                'width' => $row->width === null ? null : (int) $row->width,
                'height' => $row->height === null ? null : (int) $row->height,
                'position' => (int) $row->position,
            ];
        }

        return $photos;
    }

    /**
     * La columna posts.author solo existe si fxw_mine.py ha guardado alguna receta propia.
     */
    private function hasAuthor(): bool
    {
        return $this->hasAuthor ??= collect($this->db->select('PRAGMA table_info(posts)'))
            ->contains('name', 'author');
    }
}
