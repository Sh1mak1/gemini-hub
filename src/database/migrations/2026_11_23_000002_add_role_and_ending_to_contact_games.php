<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_games', function (Blueprint $table) {
            $table->string('character_role', 100)->nullable()->after('character_name');
            $table->text('ending')->nullable()->after('loss_reason');
        });
    }

    public function down(): void
    {
        Schema::table('contact_games', function (Blueprint $table) {
            $table->dropColumn(['character_role', 'ending']);
        });
    }
};
