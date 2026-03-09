<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RolesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Rôles essentiels
        $roles = [
            ['name' => 'Admin'],
            ['name' => 'Client'],
            ['name' => 'Vendeur'],
            ['name' => 'Commercial'],
            ['name' => 'Responsable Commercial'],
        ];

        // Nettoyer la table (optionnel)
        DB::table('roles')->delete();

        // Insérer les rôles
        DB::table('roles')->insert($roles);

        $this->command->info('✅ 2 rôles créés: admin (ID:1) et client (ID:2)');
    }
}
