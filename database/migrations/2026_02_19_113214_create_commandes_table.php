<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('commandes', function (Blueprint $table) {
            $table->id();
            $table->string('numeroCommande')->unique();
            $table->decimal('montantTotal', 10, 2);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->foreignId('panier_id')
                ->nullable()
                ->constrained('paniers')
                ->onDelete('set null');
            $table->text('noteCommande')->nullable();
            $table->enum('statut', ['en_attente', 'en_cours', 'valider'])->default('en_attente');
            // Infos client (pour commandes guests)
            $table->string('email')->nullable();
            $table->string('nom_client')->nullable();
            $table->string('prenom_client')->nullable();
            $table->string('telephone_client')->nullable();
            $table->text('adresse_client')->nullable();
            $table->string('pays')->nullable();
            $table->string('ville_zone')->nullable();
            $table->string('code_postal')->nullable();
            $table->string('region')->nullable();
            $table->string('methode_paiement')->nullable();
            $table->boolean('is_guest')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commandes');
    }
};
