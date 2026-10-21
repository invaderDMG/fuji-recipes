@php
    use App\Support\FujiTheme;
    use App\Support\MyCamera;
    use Filament\Support\Icons\Heroicon;

    $recipe = $getRecord();
@endphp

<x-fuji.photo :recipe="$recipe" class="fuji-fam-{{ FujiTheme::family($recipe) }}">
    <div class="fuji-photo-marks">
        @unless ($recipe->isCompatibleWith(MyCamera::sensorSlug()))
            <span class="fuji-mark">No figura para tu {{ MyCamera::camera() }}</span>
        @endunless
        @if ($recipe->is_favorite)
            <span class="fuji-mark" title="Favorita">{{ \Filament\Support\generate_icon_html(Heroicon::Star) }}</span>
        @endif
    </div>
</x-fuji.photo>
