<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="fuji-detail-photo fuji-fam-{{ \App\Support\FujiTheme::family($record) }}">
        <x-fuji.photo :recipe="$record" />
        <div class="fuji-scales">
            @foreach (array_keys(\App\Support\FujiTheme::SCALE_RANGE) as $field)
                <x-fuji.scale :field="$field" :value="$record->{$field}" />
            @endforeach
        </div>
    </div>
</x-dynamic-component>
