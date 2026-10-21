{{-- Ajustes en el orden del menú de la cámara, para copiarlos de arriba abajo --}}
@php
    use Filament\Support\Icons\Heroicon;
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    <div class="fuji-settings fuji-fam-{{ \App\Support\FujiTheme::family($record) }}">
        @foreach ($tiles as $tile)
            <div @class(['fuji-setting', 'is-sim' => $tile['key'] === 'film_simulation'])>
                <span class="fuji-setting-label">
                    {{ $tile['label'] }}
                    @if ($tile['locked'])
                        <span class="fuji-lock" title="Editado a mano: la importación no lo sobrescribe">
                            {{ \Filament\Support\generate_icon_html(Heroicon::LockClosed) }}
                        </span>
                    @endif
                </span>
                <b>{{ $tile['value'] ?? '—' }}</b>
                @if ($tile['note'])
                    <small>{{ $tile['note'] }}</small>
                @endif
            </div>
        @endforeach
    </div>
</x-dynamic-component>
