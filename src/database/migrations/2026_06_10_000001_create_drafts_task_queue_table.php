<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drafts_task_queue', function (Blueprint $table) {
            $table->id();
            $table->text('input_text');
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamps();

            $table->index('last_attempted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drafts_task_queue');
    }
};
