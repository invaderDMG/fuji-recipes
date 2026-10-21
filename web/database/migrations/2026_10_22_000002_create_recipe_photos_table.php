<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipe_photos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipe_id')->constrained()->cascadeOnDelete();
            // sample = foto de muestra del post (url remota); mine = foto propia subida (path en el disco public)
            $table->string('source')->index();
            $table->string('url')->nullable();
            $table->string('path')->nullable();
            $table->text('caption')->nullable();
            $table->unsignedSmallInteger('width')->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->date('taken_at')->nullable();
            $table->timestamps();
            $table->unique(['recipe_id', 'url']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipe_photos');
    }
};
