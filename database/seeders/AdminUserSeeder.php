<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Maak (of werk bij) de standaard admin-gebruiker voor het Filament-panel.
     *
     * Credentials zijn override-baar via env zodat dit ook in andere
     * omgevingen veilig draait. Idempotent: meermaals draaien is veilig.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@stockpulse.test')],
            [
                'name' => env('ADMIN_NAME', 'Stock Pulse Admin'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ],
        );
    }
}
