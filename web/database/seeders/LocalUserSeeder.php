<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario único de la app (Filament necesita uno), sin contraseña. Idempotente.
 */
class LocalUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => config('fuji.local_user.email')],
            [
                'name' => 'Local',
                'camera' => config('fuji.default_camera'),
                'camera_sensor' => config('fuji.default_sensor'),
            ],
        );
    }
}
