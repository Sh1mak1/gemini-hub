<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tourism_test_searches', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location_name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });

        Schema::table('tourism_test_spots', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('name');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down(): void
    {
        Schema::table('tourism_test_spots', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });

        Schema::table('tourism_test_searches', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
