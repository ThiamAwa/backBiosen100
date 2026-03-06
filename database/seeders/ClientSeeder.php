<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;

class ClientSeeder extends Seeder
{
    public function run(): void
    {
        $roleClient = Role::where('name', 'Client')->first();

        if (!$roleClient) {
            $this->command->error('❌ Le rôle "Client" est introuvable. Lancez RoleSeeder d\'abord.');
            return;
        }

        $clients = [
            [
                'nom' => 'Dupont',    'prenom' => 'Jean',
                'email' => 'jean.dupont@email.com',
                'adresse' => '456 Rue des Commerçants, Thiès',
                'telephone' => '771234567', 'statut' => 'actif',
            ],
            [
                'nom' => 'Fall',      'prenom' => 'Marie',
                'email' => 'marie.fall@email.com',
                'adresse' => '789 Boulevard du Sud, Saint-Louis',
                'telephone' => '772345678', 'statut' => 'actif',
            ],
            [
                'nom' => 'Diop',      'prenom' => 'Amadou',
                'email' => 'amadou.diop@email.com',
                'adresse' => '321 Avenue Bourguiba, Kaolack',
                'telephone' => '773456789', 'statut' => 'actif',
            ],
            [
                'nom' => 'Sow',       'prenom' => 'Fatou',
                'email' => 'fatou.sow@email.com',
                'adresse' => '654 Rue Khalifa, Touba',
                'telephone' => '774567890', 'statut' => 'suspendu',
            ],
            [
                'nom' => 'Ndiaye',    'prenom' => 'Ousmane',
                'email' => 'ousmane.ndiaye@email.com',
                'adresse' => '987 Boulevard du Nord, Ziguinchor',
                'telephone' => '775678901', 'statut' => 'actif',
            ],
            [
                'nom' => 'Ba',        'prenom' => 'Aissatou',
                'email' => 'aissatou.ba@email.com',
                'adresse' => '12 Rue Moussé Diop, Dakar Plateau',
                'telephone' => '776789012', 'statut' => 'actif',
            ],
            [
                'nom' => 'Mbaye',     'prenom' => 'Ibrahima',
                'email' => 'ibrahima.mbaye@email.com',
                'adresse' => '34 Cité Mixte, Rufisque',
                'telephone' => '777890123', 'statut' => 'actif',
            ],
            [
                'nom' => 'Diallo',    'prenom' => 'Mariama',
                'email' => 'mariama.diallo@email.com',
                'adresse' => '56 Avenue Cheikh Anta Diop, Dakar',
                'telephone' => '778901234', 'statut' => 'actif',
            ],
            [
                'nom' => 'Sarr',      'prenom' => 'Pape',
                'email' => 'pape.sarr@email.com',
                'adresse' => '78 Rue de Tambacounda, Tambacounda',
                'telephone' => '779012345', 'statut' => 'suspendu',
            ],
            [
                'nom' => 'Gueye',     'prenom' => 'Rokhaya',
                'email' => 'rokhaya.gueye@email.com',
                'adresse' => '90 Médina, Dakar',
                'telephone' => '770123456', 'statut' => 'actif',
            ],
            [
                'nom' => 'Thiam',     'prenom' => 'Moussa',
                'email' => 'moussa.thiam@email.com',
                'adresse' => '11 Quartier Escale, Louga',
                'telephone' => '771234560', 'statut' => 'actif',
            ],
            [
                'nom' => 'Cissé',     'prenom' => 'Ndèye',
                'email' => 'ndeye.cisse@email.com',
                'adresse' => '22 Rue du Commerce, Mbour',
                'telephone' => '772345601', 'statut' => 'actif',
            ],
            [
                'nom' => 'Traoré',    'prenom' => 'Seydou',
                'email' => 'seydou.traore@email.com',
                'adresse' => '33 Cité Lamy, Dakar',
                'telephone' => '773456012', 'statut' => 'actif',
            ],
            [
                'nom' => 'Kane',      'prenom' => 'Aminata',
                'email' => 'aminata.kane@email.com',
                'adresse' => '44 Pont, Saint-Louis',
                'telephone' => '774560123', 'statut' => 'actif',
            ],
            [
                'nom' => 'Diouf',     'prenom' => 'Lamine',
                'email' => 'lamine.diouf@email.com',
                'adresse' => '55 Quartier Biscuiterie, Dakar',
                'telephone' => '775601234', 'statut' => 'actif',
            ],
            [
                'nom' => 'Sy',        'prenom' => 'Khadija',
                'email' => 'khadija.sy@email.com',
                'adresse' => '66 Liberté 6, Dakar',
                'telephone' => '776012345', 'statut' => 'suspendu',
            ],
            [
                'nom' => 'Konaté',    'prenom' => 'Aliou',
                'email' => 'aliou.konate@email.com',
                'adresse' => '77 Cité Biagui, Ziguinchor',
                'telephone' => '770123467', 'statut' => 'actif',
            ],
            [
                'nom' => 'Badji',     'prenom' => 'Célestine',
                'email' => 'celestine.badji@email.com',
                'adresse' => '88 Rue Galandou Diouf, Dakar',
                'telephone' => '771230456', 'statut' => 'actif',
            ],
            [
                'nom' => 'Faye',      'prenom' => 'Serigne',
                'email' => 'serigne.faye@email.com',
                'adresse' => '99 Grand Dakar, Dakar',
                'telephone' => '772304567', 'statut' => 'actif',
            ],
            [
                'nom' => 'Mendy',     'prenom' => 'Angélique',
                'email' => 'angelique.mendy@email.com',
                'adresse' => '101 Rue des Jasmins, Kolda',
                'telephone' => '773045678', 'statut' => 'actif',
            ],
        ];

        $created = 0;
        foreach ($clients as $client) {
            if (!User::where('email', $client['email'])->exists()) {
                User::create(array_merge($client, [
                    'password'          => Hash::make('client123'),
                    'role_id'           => $roleClient->id,
                    'email_verified_at' => now(),
                ]));
                $created++;
            }
        }

        $this->command->info("✅ {$created} nouveaux clients créés!");
        $this->command->info("📊 Total clients: " . User::where('role_id', $roleClient->id)->count());
    }
}