<?php

namespace App\Filament\Pages;

use App\Models\Recipe;
use App\Support\MyCamera;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * «Mi cámara»: el modelo del usuario decide qué recetas son compatibles.
 *
 * @property-read Schema $form
 */
class MyCameraSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCamera;

    protected static ?string $navigationLabel = 'Mi cámara';

    protected static ?string $title = 'Mi cámara';

    protected static ?string $slug = 'my-camera';

    protected static ?int $navigationSort = 40;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(['camera' => MyCamera::sensorSlug().'|'.MyCamera::camera()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->description('Las recetas compatibles son las que Fuji X Weekly publica en el índice del sensor de tu cámara.')
                    ->schema([
                        Select::make('camera')
                            ->label('Cámara')
                            ->options(fn () => MyCamera::options())
                            ->searchable()
                            ->required()
                            ->helperText(fn () => 'Ahora: '.MyCamera::label().' · '
                                .Recipe::compatibleWith(MyCamera::sensorSlug())->count().' recetas compatibles'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Guardar')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        [$sensor, $camera] = explode('|', $this->form->getState()['camera'], 2);

        Auth::user()->update(['camera' => $camera, 'camera_sensor' => $sensor]);

        Notification::make()->success()->title('Cámara guardada: '.MyCamera::label())->send();
    }
}
