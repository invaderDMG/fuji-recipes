<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Mi cámara": modelo y slug de su sensor (sensors.slug)
            $table->string('camera')->nullable();
            $table->string('camera_sensor')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['camera', 'camera_sensor']);
        });
    }
};
