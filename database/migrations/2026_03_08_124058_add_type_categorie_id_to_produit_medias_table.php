<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
{
    Schema::table('produit_medias', function (Blueprint $table) {
        $table->foreignId('type_categorie_id')
            ->nullable()
            ->constrained('type_categories')
            ->onDelete('set null');
    });
}

public function down(): void
{
    Schema::table('produit_medias', function (Blueprint $table) {
        $table->dropForeign(['type_categorie_id']);
        $table->dropColumn('type_categorie_id');
    });
}
};
