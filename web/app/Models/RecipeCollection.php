<?php

namespace App\Models;

use Database\Factories\RecipeCollectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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

    /**
     * Fija las recetas de la colección en orden (C1, C2…). Quita y vuelve a
     * insertar todas para no chocar con el índice único de posición al reordenar.
     *
     * @param  list<int>  $recipeIds
     */
    public function setRecipes(array $recipeIds): void
    {
        $recipeIds = array_values(array_map(intval(...), $recipeIds));

        if (count($recipeIds) > self::MAX_SLOTS) {
            throw new InvalidArgumentException('Una colección tiene como máximo '.self::MAX_SLOTS.' recetas (C1–C7).');
        }

        if (count($recipeIds) !== count(array_unique($recipeIds))) {
            throw new InvalidArgumentException('Una receta no puede ocupar dos posiciones de la misma colección.');
        }

        DB::transaction(function () use ($recipeIds) {
            $this->recipes()->detach();
            foreach ($recipeIds as $i => $id) {
                $this->recipes()->attach($id, ['position' => $i + 1]);
            }
        });

        $this->unsetRelation('recipes');
    }

    /**
     * Añade recetas al final, sin duplicar las que ya están.
     *
     * @param  iterable<int>  $recipeIds
     * @return int recetas añadidas
     */
    public function appendRecipes(iterable $recipeIds): int
    {
        $current = $this->recipes()->pluck('recipes.id')->all();
        $new = array_values(array_diff(array_map(intval(...), [...$recipeIds]), $current));

        $this->setRecipes([...$current, ...$new]);

        return count($new);
    }
}
