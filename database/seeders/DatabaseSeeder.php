<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'Super Admin',
            'Admin',
            'Client',
            'Vendeur',
            'Commercial',
            'Responsable Commercial',
            'Livreur',
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }

        // Créer un Super Admin par défaut
        \App\Models\User::firstOrCreate(
            ['email' => 'admin@biosen.sn'],
            [
                'nom'      => 'Admin',
                'prenom'   => 'Super',
                'telephone'=> '000000000',
                'adresse'  => 'Dakar',
                'password' => \Illuminate\Support\Facades\Hash::make('passer'),
                'role_id'  => Role::where('name', 'Super Admin')->first()->id,
            ]
        );
    }
}
