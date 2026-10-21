<?php

namespace App\Http\Controllers\Api;

use App\Enums\RecipeSource;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListRecipesRequest;
use App\Http\Requests\SimilarRecipesRequest;
use App\Http\Requests\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\SimilarityResource;
use App\Models\Recipe;
use App\Models\Sensor;
use App\Models\Tag;
use App\Services\RecipeSimilarity;
use App\Support\MyCamera;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class RecipeController extends Controller
{
    /**
     * GET /api/recipes — mismos filtros que el listado de la interfaz.
     */
    public function index(ListRecipesRequest $request): AnonymousResourceCollection
    {
        $f = $request->validated();
        $flag = fn (string $key) => array_key_exists($key, $f) ? (bool) $f[$key] : null;

        $query = Recipe::query()
            ->with(['sensors', 'tags', 'photos'])
            ->when($f['q'] ?? null, fn (Builder $q, string $term) => $q->search($term))
            ->when($f['simulation'] ?? null, fn (Builder $q, array $v) => $q->whereIn('film_simulation', $v))
            ->when($f['family'] ?? null, fn (Builder $q, array $v) => $q->inFamilies($v))
            ->when($f['dr'] ?? null, fn (Builder $q, array $v) => $q->whereIn('dynamic_range', $v))
            ->when($f['sensor'] ?? null, fn (Builder $q, array $v) => $q->forSensors($v))
            ->when($f['source'] ?? null, fn (Builder $q, string $v) => $q->where('source', $v))
            ->when($f['cce'] ?? null, fn (Builder $q, array $v) => $q->whereIn('color_chrome_effect', $v))
            ->when($f['fx_blue'] ?? null, fn (Builder $q, array $v) => $q->whereIn('color_chrome_fx_blue', $v))
            ->when($f['grain'] ?? null, fn (Builder $q, array $v) => $q->whereIn('grain_roughness', $v))
            ->when($f['wb_mode'] ?? null, fn (Builder $q, array $v) => $q->whereIn('wb_mode', $v))
            ->when($f['tag'] ?? null, fn (Builder $q, array $v) => $q->whereHas('tags', fn (Builder $t) => $t->whereIn('name', $v)))
            ->when($flag('favorite') !== null, fn (Builder $q) => $q->where('is_favorite', $flag('favorite')))
            ->when($flag('needs_review') !== null, fn (Builder $q) => $q->where('needs_review', $flag('needs_review')))
            ->when($flag('locked') !== null, fn (Builder $q) => $q->withLockedFields($flag('locked')))
            ->when($flag('compatible') !== null, fn (Builder $q) => $q->compatibleWith(MyCamera::sensorSlug(), $flag('compatible')))
            ->when($flag('missing') !== null, fn (Builder $q) => $flag('missing')
                ? $q->whereNotNull('missing_from_source_at')
                : $q->whereNull('missing_from_source_at'));

        foreach (['highlight', 'shadow', 'color'] as $field) {
            $query->inRange($field, $f["{$field}_min"] ?? null, $f["{$field}_max"] ?? null);
        }

        $sort = $f['sort'] ?? '-published_at';
        $query->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')->orderBy('id');

        return RecipeResource::collection($query->paginate($f['per_page'] ?? 25)->withQueryString());
    }

    public function show(Recipe $recipe): RecipeResource
    {
        return new RecipeResource($recipe->load(['sensors', 'tags', 'photos']));
    }

    /**
     * GET /api/recipes/{recipe}/similar?n=&sensor=&same_sim=
     */
    public function similar(SimilarRecipesRequest $request, Recipe $recipe, RecipeSimilarity $similarity): AnonymousResourceCollection
    {
        $f = $request->validated();
        $results = $similarity->nearest($recipe, (int) ($f['n'] ?? 10), $f['sensor'] ?? null, (bool) ($f['same_sim'] ?? false));
        $results->each(fn ($r) => $r->recipe->loadMissing(['sensors', 'tags', 'photos']));

        return SimilarityResource::collection($results)->additional([
            'recipe' => ['id' => $recipe->id, 'name' => $recipe->name],
        ]);
    }

    /**
     * POST /api/recipes — crea una receta propia (source = manual).
     */
    public function store(StoreRecipeRequest $request): JsonResponse
    {
        $data = $request->validated();

        $recipe = DB::transaction(function () use ($data) {
            $recipe = Recipe::create([
                ...Arr::except($data, ['sensors', 'tags']),
                'source' => RecipeSource::Manual,
            ]);
            $recipe->sensors()->sync(Sensor::whereIn('slug', $data['sensors'] ?? [])->pluck('id'));
            $recipe->tags()->sync(collect($data['tags'] ?? [])->map(fn (string $name) => Tag::firstOrCreate(['name' => $name])->id));

            return $recipe;
        });

        return (new RecipeResource($recipe->load(['sensors', 'tags', 'photos'])))
            ->response()
            ->setStatusCode(201)
            ->header('Location', route('api.recipes.show', $recipe));
    }
}
