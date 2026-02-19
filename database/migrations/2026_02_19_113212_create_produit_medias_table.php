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
            $table->foreignId('produit_id')
                ->constrained('produits')
                ->onDelete('cascade');
            $table->enum('type', ['image', 'video_url'])->default('image');
            $table->string('chemin')->nullable();
            $table->string('url_externe')->nullable();
            $table->string('titre')->nullable();
            $table->integer('ordre')->default(0);
            $table->boolean('est_principal')->default(false);
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
