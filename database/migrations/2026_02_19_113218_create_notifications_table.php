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
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->enum('type', [
                'commande', 'paiement', 'livraison',
                'promotion', 'stock', 'newProduit', 'retour'
            ]);
            $table->text('message');
            $table->boolean('lu')->default(false);
            $table->foreignId('user_id')
                ->constrained('users')
                ->onDelete('cascade');
            $table->foreignId('produit_id')
                ->nullable()
                ->constrained('produits')
                ->onDelete('set null');
            $table->foreignId('paiement_id')
                ->nullable()
                ->constrained('paiements')
                ->onDelete('set null');
            $table->foreignId('commande_id')
                ->nullable()
                ->constrained('commandes')
                ->onDelete('set null');
            $table->foreignId('livraison_id')
                ->nullable()
                ->constrained('livraisons')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
