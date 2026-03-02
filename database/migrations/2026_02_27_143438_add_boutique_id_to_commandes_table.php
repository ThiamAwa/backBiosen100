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
        Schema::table('commandes', function (Blueprint $table) {
            // Ajout de la colonne boutique_id avec clé étrangère
            $table->foreignId('boutique_id')
                  ->nullable()
                  ->after('panier_id') 
                  ->constrained('boutiques')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('commandes', function (Blueprint $table) {
            // Suppression de la clé étrangère puis de la colonne
            $table->dropForeign(['boutique_id']);
            $table->dropColumn('boutique_id');
        });
    }
};