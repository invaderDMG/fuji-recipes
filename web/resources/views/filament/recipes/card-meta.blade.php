@php
    $recipe = $getRecord();
    $parts = array_filter([
        $recipe->author,
        $recipe->source->label(),
        $recipe->published_at?->format('d/m/Y'),
    ]);
@endphp

<p class="fuji-card-meta">{{ implode(', ', $parts) }}</p>
