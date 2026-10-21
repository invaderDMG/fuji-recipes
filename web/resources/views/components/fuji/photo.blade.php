{{-- Foto de la receta con la banda de su simulación, como el borde de una película --}}
@props(['recipe', 'band' => true])

<div {{ $attributes->class('fuji-photo') }}>
    @if ($recipe->image_url)
        <img src="{{ $recipe->image_url }}" alt="" loading="lazy" decoding="async">
    @endif
    @if ($band)
        <div class="fuji-band">
            <span>{{ $recipe->film_simulation?->label() ?? $recipe->film_simulation_raw ?? 'Sin simulación' }}</span>
            @if ($recipe->dynamic_range)
                <span>{{ $recipe->dynamic_range->label() }}</span>
            @endif
        </div>
    @endif
    {{ $slot }}
</div>
