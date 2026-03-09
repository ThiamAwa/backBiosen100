<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        if (!User::where('email', 'admin@gmail.com')->exists()) {
            User::create([
                'nom'               => 'Admin',
                'prenom'            => 'Super',
                'email'             => 'admin@gmail.com',
                'password'          => Hash::make('passer123'),
                'role_id'           => 1,
                'statut'            => 'actif',
                'adresse'           => '123 Avenue de l\'Administration, Dakar',
                'telephone'         => '776457837',
                'email_verified_at' => now(),
            ]);
            $this->command->info('✅ Administrateur créé avec succès!');
        } else {
            $this->command->info('ℹ️  Administrateur existe déjà.');
        }
    }
}