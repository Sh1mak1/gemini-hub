<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tourism_test_searches', function (Blueprint $table) {
            $table->id();
            $table->string('location_name', 100);
            $table->string('status', 20);
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('tourism_test_spots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tourism_test_search_id')
                ->constrained('tourism_test_searches')
                ->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order');
            $table->string('name', 200);
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->string('distance_text', 50);
            $table->text('description');
            $table->string('image_url', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tourism_test_spots');
        Schema::dropIfExists('tourism_test_searches');
    }
};
