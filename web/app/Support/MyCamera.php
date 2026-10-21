<?php

namespace App\Support;

use App\Models\Sensor;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * La cámara del usuario («Mi cámara»). Una receta es compatible si aparece en
 * el índice de Fuji X Weekly del sensor de la cámara.
 */
final class MyCamera
{
    public static function camera(): string
    {
        return self::user()?->camera ?? config('fuji.default_camera');
    }

    public static function sensorSlug(): string
    {
        return self::user()?->camera_sensor ?? config('fuji.default_sensor');
    }

    public static function sensor(): ?Sensor
    {
        return Sensor::where('slug', self::sensorSlug())->first();
    }

    /**
     * "X-T30 III (X-Trans V)"
     */
    public static function label(): string
    {
        return self::camera().' ('.(self::sensor()->label ?? self::sensorSlug()).')';
    }

    /**
     * Cámaras de cada sensor para el selector, con clave "slug|cámara".
     *
     * @return array<string, array<string, string>>
     */
    public static function options(): array
    {
        return Sensor::orderBy('id')->get()->mapWithKeys(fn (Sensor $sensor) => [
            $sensor->label => collect(explode(',', (string) $sensor->cameras))
                ->map(fn (string $camera) => trim($camera))
                ->filter()
                ->mapWithKeys(fn (string $camera) => ["{$sensor->slug}|{$camera}" => $camera])
                ->all(),
        ])->filter()->all();
    }

    /**
     * El usuario autenticado o, sin sesión (API), el usuario local.
     */
    private static function user(): ?User
    {
        return Auth::user() ?? once(fn () => User::firstWhere('email', config('fuji.local_user.email')));
    }
}
