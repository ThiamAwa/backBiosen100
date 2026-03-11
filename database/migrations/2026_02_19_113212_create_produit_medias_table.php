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
        Schema::create('produit_medias', function (Blueprint $table) {
            $table->id();
            $table->json('image')->nullable();
            $table->string('video')->nullable();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2);
            $table->integer('stock')->default(0);
            $table->decimal('prixPromo', 10, 2)->nullable();
            $table->boolean('enPromotion')->default(false);
            $table->decimal('noteProduit', 3, 1)->nullable();
            $table->integer('ordre')->default(0);
            $table->foreignId('type_categorie_id')
            ->nullable()
            ->constrained('type_categories')
            ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produit_medias');
    }
};
