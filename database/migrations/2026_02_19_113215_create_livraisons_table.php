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
        Schema::create('livraisons', function (Blueprint $table) {
            $table->id();
            $table->string('zone');
            $table->enum('statut', ['en_attente', 'en_cours', 'en_livraison', 'livree', 'annulee'])
                ->default('en_attente');
            $table->string('telephone');
            $table->decimal('frais', 10, 2)->default(0);
            $table->string('pays')->nullable();
            $table->text('adresse')->nullable();
            $table->string('nom_client')->nullable();
            $table->string('prenom_client')->nullable();
            $table->foreignId('commande_id')
                ->nullable()
                ->constrained('commandes')
                ->onDelete('set null');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('livraisons');
    }
};
