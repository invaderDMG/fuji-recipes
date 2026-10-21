<?php

namespace App\Import;

use App\Enums\PhotoSource;
use App\Enums\RecipeSource;
use App\Models\Recipe;
use App\Models\Sensor;
use BackedEnum;
use DateTimeInterface;
use DomainException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Importa recetas de fujixweekly.db a la BD de Laravel por clave natural
 * (source_url + block_index), sin tocar los campos bloqueados ni borrar nada.
 */
final class RecipeImporter
{
    public function __construct(
        private readonly SourceReader $reader,
        private readonly SourceRecipeMapper $mapper = new SourceRecipeMapper,
        private readonly DatabaseBackup $backup = new DatabaseBackup,
    ) {}

    /**
     * Calcula el diff y, solo si $write es true, hace la copia de seguridad y escribe.
     */
    public function run(?RecipeSource $only = null, bool $write = false): ImportReport
    {
        $report = new ImportReport;
        $sensorsByPost = $this->reader->sensors();
        $photosByPost = $this->reader->photos();

        $existing = Recipe::withTrashed()
            ->with('sensors')
            ->whereIn('source', $only ? [$only] : [RecipeSource::Fxw, RecipeSource::Mine])
            ->get()
            ->keyBy(fn (Recipe $r) => "{$r->source_url}#{$r->block_index}");

        $pending = [];
        $seen = [];

        foreach ($this->reader->recipes($only) as $row) {
            $mapped = $this->mapper->map($row, $sensorsByPost[$row->post_url] ?? []);
            $seen[$mapped->key()] = true;

            if ($existing->get($mapped->key())?->trashed()) {
                $report->skippedDeleted[] = $this->label($existing->get($mapped->key()));

                continue;
            }

            $recipe = $existing[$mapped->key()] ?? new Recipe([
                'source' => $mapped->source,
                'source_url' => $mapped->sourceUrl,
                'block_index' => $mapped->blockIndex,
            ]);

            $photos = $photosByPost[$mapped->sourceUrl] ?? [];
            $pending[] = [$recipe, $mapped->sensors, $photos];
            $this->apply($recipe, $mapped, $report);
            $report->photosAdded += count($this->newPhotos($recipe, $photos));
        }

        $missing = $existing->filter(fn (Recipe $r, string $key) => ! isset($seen[$key]) && ! $r->trashed() && $r->missing_from_source_at === null);
        foreach ($missing as $recipe) {
            $report->missing[] = $this->label($recipe);
        }

        $report->newSensors = collect($pending)
            ->flatMap(fn (array $p) => array_keys($p[1]))
            ->unique()
            ->diff(Sensor::pluck('slug'))
            ->values()
            ->all();

        if (! $write) {
            return $report;
        }

        $report->backup = $this->backup->create();

        DB::transaction(function () use ($pending, $missing) {
            $now = now();

            foreach ($pending as [$recipe, $sensors, $photos]) {
                $recipe->imported_at = $now;
                // Si solo cambian raw_json o imported_at, no se toca updated_at
                $recipe->timestamps = Arr::except($recipe->getDirty(), ['raw_json', 'review_issues', 'imported_at']) !== [];
                $recipe->save();
                $recipe->timestamps = true;
                $this->syncSensors($recipe, $sensors);
                $this->addPhotos($recipe, $photos);
            }

            Recipe::whereKey($missing->modelKeys())->update(['missing_from_source_at' => $now]);
        });

        $report->written = true;

        return $report;
    }

    /**
     * Quita los bloqueos de una receta y vuelve a cargar sus valores desde el origen.
     */
    public function restore(Recipe $recipe): ImportReport
    {
        if (! $recipe->source->isImported()) {
            throw new DomainException('Solo se pueden restaurar recetas importadas.');
        }

        if ($recipe->trashed()) {
            throw new DomainException('La receta está borrada: recupérala antes de restaurar sus valores de origen.');
        }

        $row = $this->reader->find($recipe->source_url, $recipe->block_index)
            ?? throw new DomainException("La receta ya no está en el origen: {$recipe->source_url}#{$recipe->block_index}");

        $sensors = $this->reader->sensors($recipe->source_url)[$recipe->source_url] ?? [];
        $photos = $this->reader->photos($recipe->source_url)[$recipe->source_url] ?? [];
        $report = new ImportReport;

        DB::transaction(function () use ($recipe, $row, $sensors, $photos, $report) {
            $recipe->load('sensors');
            $recipe->locked_fields = [];
            $this->apply($recipe, $this->mapper->map($row, $sensors), $report);
            $recipe->imported_at = now();
            $recipe->save();
            $this->syncSensors($recipe, $sensors);
            $this->addPhotos($recipe, $photos);
        });

        return $report;
    }

    private function apply(Recipe $recipe, MappedRecipe $mapped, ImportReport $report): void
    {
        $locked = array_intersect($recipe->locked_fields ?? [], Recipe::LOCKABLE);
        $attributes = Arr::except($mapped->attributes, ['raw_json']);
        $lockedAttributes = Arr::only($attributes, $locked);
        $issues = Arr::except($mapped->issues, $locked);
        $label = $this->label($recipe, $attributes['name']);

        if ($recipe->exists && $lockedAttributes !== []) {
            $probe = (clone $recipe)->fill($lockedAttributes);
            foreach (array_keys($probe->getDirty()) as $field) {
                $report->locked[$label][$field] = [$this->display($recipe->{$field}), $this->display($probe->{$field})];
            }
        }

        $reviewIssues = Arr::except($mapped->reviewIssues, $locked);
        $recipe->fill(Arr::except($attributes, $locked) + ['needs_review' => $issues !== []]);

        if ($recipe->missing_from_source_at !== null) {
            $recipe->missing_from_source_at = null;
            $report->reappeared[] = $label;
        }

        $changes = [];
        foreach (array_keys($recipe->getDirty()) as $field) {
            $changes[$field] = [$this->display($recipe->getOriginal($field)), $this->display($recipe->{$field})];
        }

        $current = $recipe->exists
            ? $recipe->sensors->mapWithKeys(fn (Sensor $s) => [$s->slug => $s->pivot->index_label])->sortKeys()->all()
            : [];
        $desired = collect($mapped->sensors)->sortKeys()->all();
        if ($current !== $desired) {
            $changes['sensors'] = [implode(', ', array_keys($current)) ?: '∅', implode(', ', array_keys($desired)) ?: '∅'];
        }

        // raw_json y review_issues se actualizan siempre y no cuentan como cambio
        $recipe->raw_json = $mapped->attributes['raw_json'];
        $recipe->review_issues = $reviewIssues ?: null;

        match (true) {
            ! $recipe->exists => $report->created[] = $label,
            $changes !== [] => $report->updated[$label] = $changes,
            default => $report->unchanged++,
        };

        if ($issues !== []) {
            $report->needsReview++;
        }
        foreach ($issues as $field => $reason) {
            $key = "{$field}: {$reason}";
            $report->reviewReasons[$key] = ($report->reviewReasons[$key] ?? 0) + 1;
        }
        if (isset($issues['film_simulation']) && $attributes['film_simulation_raw'] !== null) {
            $raw = $attributes['film_simulation_raw'];
            $report->unmappedSimulations[$raw] = ($report->unmappedSimulations[$raw] ?? 0) + 1;
        }
    }

    /**
     * @param  array<string, string|null>  $sensors  slug => index_label
     */
    private function syncSensors(Recipe $recipe, array $sensors): void
    {
        $sync = [];

        foreach ($sensors as $slug => $indexLabel) {
            $sensor = Sensor::firstOrCreate(['slug' => $slug], ['label' => $slug]);
            $sync[$sensor->id] = ['index_label' => $indexLabel];
        }

        $recipe->sensors()->sync($sync);
        $recipe->unsetRelation('sensors');
    }

    /**
     * Fotos de muestra del origen que la receta aún no tiene (por URL).
     *
     * @param  list<array<string, mixed>>  $photos
     * @return list<array<string, mixed>>
     */
    private function newPhotos(Recipe $recipe, array $photos): array
    {
        if ($photos === [] || ! $recipe->exists) {
            return $photos;
        }

        $known = $recipe->samplePhotos()->pluck('url')->all();

        return array_values(array_filter($photos, fn (array $p) => ! in_array($p['url'], $known, true)));
    }

    /**
     * Añade las fotos de muestra nuevas. No borra las que ya no estén en el
     * origen ni toca las fotos propias.
     *
     * @param  list<array<string, mixed>>  $photos
     */
    private function addPhotos(Recipe $recipe, array $photos): void
    {
        foreach ($this->newPhotos($recipe, $photos) as $photo) {
            $recipe->photos()->create([...$photo, 'source' => PhotoSource::Sample]);
        }
    }

    private function label(Recipe $recipe, ?string $name = null): string
    {
        return ($name ?? $recipe->name)." [{$recipe->source_url}#{$recipe->block_index}]";
    }

    private function display(mixed $value): string
    {
        return match (true) {
            $value === null => '∅',
            $value instanceof BackedEnum => (string) $value->value,
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            is_bool($value) => $value ? 'sí' : 'no',
            is_array($value) => json_encode($value, JSON_UNESCAPED_UNICODE),
            default => (string) $value,
        };
    }
}
