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
        Schema::create('produits', function (Blueprint $table) {
            $table->id();
            $table->string('image')->nullable();
            $table->string('video')->nullable();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->decimal('prix', 10, 2);
            $table->integer('stock')->default(0);
            $table->decimal('prixPromo', 10, 2)->nullable();
            $table->string('modeUtilisation')->nullable();
            $table->boolean('enPromotion')->default(false);
            $table->decimal('noteProduit', 3, 1)->nullable();
            $table->foreignId('categorie_id')
                ->nullable()
                ->constrained('categories')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('produits');
    }
};
