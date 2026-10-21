<?php

namespace App\Models;

use App\Enums\PhotoSource;
use Database\Factories\RecipePhotoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Foto de una receta: de muestra (URL del post en fujixweekly.com) o propia
 * (fichero subido al disco "public").
 */
class RecipePhoto extends Model
{
    /** @use HasFactory<RecipePhotoFactory> */
    use HasFactory;

    public const DISK = 'public';

    public const DIRECTORY = 'photos';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source' => PhotoSource::class,
            'width' => 'integer',
            'height' => 'integer',
            'position' => 'integer',
            'taken_at' => 'date',
        ];
    }

    protected static function booted(): void
    {
        // Al borrar una foto propia se borra también su fichero
        static::deleted(function (RecipePhoto $photo) {
            if ($photo->path !== null) {
                Storage::disk(self::DISK)->delete($photo->path);
            }
        });
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function src(): string
    {
        return $this->path !== null ? Storage::disk(self::DISK)->url($this->path) : $this->url;
    }

    /**
     * Miniatura: las fotos de muestra se piden redimensionadas al CDN de WordPress.
     */
    public function thumbnail(int $width = 480): string
    {
        return $this->path === null && str_contains((string) $this->url, 'i0.wp.com/')
            ? "{$this->url}?w={$width}"
            : $this->src();
    }
}
