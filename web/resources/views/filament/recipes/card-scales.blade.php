@php
    $recipe = $getRecord();
@endphp

<div class="fuji-scales fuji-fam-{{ \App\Support\FujiTheme::family($recipe) }}">
    @foreach (['highlight', 'shadow', 'color'] as $field)
        <x-fuji.scale :field="$field" :value="$recipe->{$field}" />
    @endforeach
</div>
