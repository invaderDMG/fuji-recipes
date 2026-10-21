<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_sensor', function (Blueprint $table) {
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sensor_id')->constrained()->cascadeOnDelete();
            // Título con el que aparece la receta en el índice de ese sensor
            $table->string('index_label')->nullable();
            $table->primary(['recipe_id', 'sensor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_sensor');
    }
};
