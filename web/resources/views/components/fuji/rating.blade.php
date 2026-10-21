{{-- Valoración de 0 a 10 como una fila de segmentos --}}
@props(['value'])

<div {{ $attributes->class('fuji-rating') }} title="Valoración">
    <span class="fuji-rating-bar" aria-hidden="true">
        @for ($i = 1; $i <= \App\Models\Recipe::RATING_MAX; $i++)
            <i @class(['is-on' => $i <= $value])></i>
        @endfor
    </span>
    <b>{{ \App\Support\RecipeFields::rating($value) }}</b>
</div>
