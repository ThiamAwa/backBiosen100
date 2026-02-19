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
        Schema::create('temoignages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->onDelete('set null');
            $table->string('nom_client')->nullable();
            $table->foreignId('gamme_id')
                ->nullable()
                ->constrained('gammes')
                ->onDelete('set null');
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->json('images')->nullable();
            $table->boolean('afficher')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('temoignages');
    }
};
