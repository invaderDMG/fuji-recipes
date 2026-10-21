<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recipes', function (Blueprint $table) {
            // Identidad
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('source')->index();
            $table->string('source_url')->nullable();
            $table->unsignedInteger('block_index')->default(0);
            $table->unique(['source_url', 'block_index']);

            // Metadatos
            $table->string('author')->nullable();
            $table->date('published_at')->nullable();
            $table->string('image_url')->nullable();

            // Ajustes
            $table->string('film_simulation')->nullable()->index();
            $table->string('film_simulation_raw')->nullable();
            $table->string('dynamic_range')->nullable();
            $table->string('d_range_priority')->nullable();
            $table->string('grain_roughness')->nullable();
            $table->string('grain_size')->nullable();
            $table->string('color_chrome_effect')->nullable();
            $table->string('color_chrome_fx_blue')->nullable();
            $table->string('wb_mode')->nullable();
            $table->unsignedSmallInteger('wb_kelvin')->nullable();
            $table->tinyInteger('wb_shift_red')->nullable();
            $table->tinyInteger('wb_shift_blue')->nullable();
            $table->string('white_balance_raw')->nullable();
            $table->decimal('highlight', 3, 1)->nullable();
            $table->decimal('shadow', 3, 1)->nullable();
            $table->decimal('color', 3, 1)->nullable();
            $table->decimal('sharpness', 3, 1)->nullable();
            $table->decimal('high_iso_nr', 3, 1)->nullable();
            $table->decimal('clarity', 3, 1)->nullable();
            $table->string('monochromatic_color')->nullable();
            $table->string('smooth_skin')->nullable();
            $table->string('tone_curve')->nullable();
            $table->string('iso_raw')->nullable();
            $table->unsignedInteger('iso_max')->nullable();
            $table->string('exposure_compensation')->nullable();

            // Origen e importación
            $table->json('raw_json')->nullable();
            $table->json('locked_fields')->default('[]');
            $table->boolean('needs_review')->default(false)->index();
            $table->timestamp('missing_from_source_at')->nullable();
            $table->timestamp('imported_at')->nullable();

            // Personales
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('rating')->nullable();
            $table->boolean('is_favorite')->default(false);
            $table->timestamp('tested_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recipes');
    }
};
