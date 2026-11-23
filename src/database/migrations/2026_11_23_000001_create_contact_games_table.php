<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->text('sentence');
            $table->text('situation');
            $table->string('character_name', 100);
            $table->text('channel');
            $table->text('clear_condition');
            $table->string('resource_name', 100);
            $table->unsignedSmallInteger('resource_remaining');
            $table->unsignedSmallInteger('resource_max');
            $table->text('resource_rule');
            $table->text('last_change_note')->nullable();
            $table->text('loss_reason')->nullable();
            $table->json('hidden_state');
            $table->json('messages');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_games');
    }
};
