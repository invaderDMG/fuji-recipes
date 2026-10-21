<?php

namespace App\Models;

use Database\Factories\RecipeCollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Conjunto de recetas para los slots C1–C7 de la cámara (tabla "collections";
 * el modelo no se llama Collection para no chocar con Illuminate\Support\Collection).
 */
class RecipeCollection extends Model
{
    /** @use HasFactory<RecipeCollectionFactory> */
    use HasFactory;

    public const MAX_SLOTS = 7;

    protected $table = 'collections';

    protected $guarded = ['id'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class, 'collection_recipe', 'collection_id', 'recipe_id')
            ->withPivot('position')
            ->orderByPivot('position');
    }
}
