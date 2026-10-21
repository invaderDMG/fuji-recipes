<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Usuario único de la app local (Filament necesita uno). Idempotente.
 */
class LocalUserSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => config('fuji.local_user.email')],
            ['name' => 'Local', 'password' => config('fuji.local_user.password')],
        );
    }
}
