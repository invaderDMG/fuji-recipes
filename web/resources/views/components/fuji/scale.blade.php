{{-- Un ajuste sobre la escala de la cámara: marca por cada paso y punto en el valor --}}
@props(['field', 'value', 'label' => null])

@php
    $scale = \App\Support\FujiTheme::scale($field, $value);
@endphp

<div {{ $attributes->class('fuji-scale') }}>
    <span class="fuji-scale-label">{{ $label ?? \App\Support\RecipeFields::label($field) }}</span>
    @if ($scale)
        <span
            class="fuji-scale-track"
            style="--steps: {{ $scale['max'] - $scale['min'] }}; --zero: {{ $scale['zero'] }}%"
            aria-hidden="true"
        >
            <i style="left: {{ $scale['from'] }}%; width: {{ $scale['width'] }}%"></i>
            <u style="left: {{ $scale['pos'] }}%"></u>
        </span>
    @else
        <span class="fuji-scale-track is-empty" aria-hidden="true"></span>
    @endif
    <b>{{ \App\Support\RecipeFields::signed($value) ?? '—' }}</b>
</div>
