<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fallback_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('raw_input');
            $table->date('due_date')->nullable();
            $table->string('category')->default('other');
            $table->string('location')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->index(['status', 'due_date']);
            $table->index('category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fallback_tasks');
    }
};
