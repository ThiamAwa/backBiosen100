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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commande_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->string('numero_facture')->unique();
       
            $table->timestamp('date_emission')->useCurrent();
            $table->timestamp('date_echeance')->nullable();
            $table->enum('statut_paiement', ['payé', 'impayé', 'en_attente'])->default('en_attente');
            $table->json('metadonnees')->nullable();
            $table->timestamps();

            $table->index('numero_facture');
            $table->index('date_emission');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};