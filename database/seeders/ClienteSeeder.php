<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Auth\Events\Registered;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        // Evitar duplicados
        $user = User::where('email', 'hrafartist@gmail.com')->first();

        if (!$user) {
            $user = User::create([
                'name' => 'Cliente de prueba',
                'email' => 'hrafartist@gmail.com',
                'password' => Hash::make('decora10'),
                'role' => 'cliente',
            ]);

            // Disparar evento de verificación
            event(new Registered($user));
        }
    }
}
