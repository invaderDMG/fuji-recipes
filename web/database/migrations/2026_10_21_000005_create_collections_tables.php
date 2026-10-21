<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Slots C1–C7 de la cámara
        Schema::create('collection_recipe', function (Blueprint $table) {
            $table->foreignId('collection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->primary(['collection_id', 'recipe_id']);
            $table->unique(['collection_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_recipe');
        Schema::dropIfExists('collections');
    }
};
