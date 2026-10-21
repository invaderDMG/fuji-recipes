<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @if ($getState() !== null)
        <x-fuji.rating :value="$getState()" />
    @else
        <span class="fuji-card-meta">Sin valorar</span>
    @endif
</x-dynamic-component>
