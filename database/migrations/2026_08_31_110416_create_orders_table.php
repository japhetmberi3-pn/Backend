<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Créer la table orders.
     */
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained()
                ->onDelete('cascade');

            $table->foreignId('product_id')
                ->constrained()
                ->onDelete('cascade');

            $table->unsignedInteger('quantity');

            $table->string('status')->default('completed');

            $table->timestamps();
        });
    }

    /**
     * Supprimer la table orders.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
