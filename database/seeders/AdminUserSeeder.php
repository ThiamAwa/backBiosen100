<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {

        // 1. S'assurer que la table roles a les données nécessaires


        // 2. Créer l'administrateur seulement s'il n'existe pas
        if (!User::where('email', 'admin@gmail.com')->exists()) {
            User::create([
                'nom' => 'Admin',
                'prenom' => 'Super',
                'email' => 'admin@gmail.com',
                'password' => Hash::make('passer123'),
                'role_id' => 1,
                'adresse' => '123 Avenue de l\'Administration, Dakar',
                'telephone' => '776457837'
            ]);
            $this->command->info('✅ Administrateur créé avec succès!');
        } else {
            $this->command->info('ℹ️  Administrateur existe déjà.');
        }

        // 3. Créer des clients seulement s'ils n'existent pas
        $clients = [
            [
                'nom' => 'Dupont',
                'prenom' => 'Jean',
                'email' => 'jean.dupont@email.com',
                'password' => Hash::make('client123'),
                'role_id' => 2, // Client
                'adresse' => '456 Rue des Commerçants, Thiès',
                'telephone' => '771234567'
            ],
            [
                'nom' => 'Fall',
                'prenom' => 'Marie',
                'email' => 'marie.fall@email.com',
                'password' => Hash::make('client123'),
                'role_id' => 2,
                'adresse' => '789 Boulevard du Sud, Saint-Louis',
                'telephone' => '772345678'
            ],
            [
                'nom' => 'Diop',
                'prenom' => 'Amadou',
                'email' => 'amadou.diop@email.com',
                'password' => Hash::make('client123'),
                'role_id' => 2,
                'adresse' => '321 Avenue Bourguiba, Kaolack',
                'telephone' => '773456789'
            ],
            [
                'nom' => 'Sow',
                'prenom' => 'Fatou',
                'email' => 'fatou.sow@email.com',
                'password' => Hash::make('client123'),
                'role_id' => 2,
                'adresse' => '654 Rue Khalifa, Touba',
                'telephone' => '774567890'
            ],
            [
                'nom' => 'Ndiaye',
                'prenom' => 'Ousmane',
                'email' => 'ousmane.ndiaye@email.com',
                'password' => Hash::make('client123'),
                'role_id' => 2,
                'adresse' => '987 Boulevard du Nord, Ziguinchor',
                'telephone' => '775678901'
            ]
        ];

        $clientsCreated = 0;
        foreach ($clients as $client) {
            if (!User::where('email', $client['email'])->exists()) {
                User::create($client);
                $clientsCreated++;
            }
        }

        $this->command->info("✅ {$clientsCreated} nouveaux clients créés avec succès!");
        $this->command->info("📊 Total utilisateurs dans la base: " . User::count());
    }


}

