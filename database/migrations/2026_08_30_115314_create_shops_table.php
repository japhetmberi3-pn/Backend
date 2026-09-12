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
        Schema::create('shops', function (Blueprint $table) {
            $table->id();

            // Vendeur propriétaire de la boutique
            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            // Informations de la boutique
            $table->string('name');
            $table->text('description')->nullable();

            $table->timestamps();

            // Un vendeur ne peut avoir qu'une seule boutique
            $table->unique('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shops');
    }
};