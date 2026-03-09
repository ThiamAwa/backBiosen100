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
        Schema::table('boutiques', function (Blueprint $table) {
            $table->string('nom')->nullable()->change();
            $table->string('adresse')->nullable()->change();
            $table->string('localisation')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('boutiques', function (Blueprint $table) {
            $table->string('nom')->nullable(false)->change();
            $table->string('adresse')->nullable(false)->change();
            $table->string('localisation')->nullable(false)->change();
        });
    }
};