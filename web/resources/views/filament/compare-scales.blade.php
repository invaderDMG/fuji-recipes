{{-- Cada ajuste numérico con una escala por receta, para ver las diferencias por la forma --}}
<div class="fuji-compare">
    @foreach (array_keys(\App\Support\FujiTheme::SCALE_RANGE) as $field)
        <div class="fuji-compare-field">
            <h4>{{ \App\Support\RecipeFields::label($field) }}</h4>
            @foreach ($recipes as $recipe)
                <x-fuji.scale
                    :field="$field"
                    :value="$recipe->{$field}"
                    :label="$recipe->name"
                    class="fuji-fam-{{ \App\Support\FujiTheme::family($recipe) }}"
                />
            @endforeach
        </div>
    @endforeach
</div>
