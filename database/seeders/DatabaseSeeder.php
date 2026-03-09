<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Désactiver les contraintes FK pour PostgreSQL
        DB::statement('SET session_replication_role = replica;');

        $this->call([
            RolesTableSeeder::class,
            AdminUserSeeder::class,
            ClientSeeder::class,  
            CommandesTableSeeder::class,
            FactureSeeder::class,
        ]);

        // Réactiver les contraintes FK
        DB::statement('SET session_replication_role = DEFAULT;');
    }
}