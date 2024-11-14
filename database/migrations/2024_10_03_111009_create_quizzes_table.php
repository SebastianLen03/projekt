<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quizzes', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->boolean('is_public')->default(false);
            $table->boolean('multiple_attempts')->default(false);
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->integer('time_limit')->nullable()->default(null);
            $table->boolean('has_passing_criteria')->default(false);
            $table->integer('passing_score')->nullable();
            $table->integer('passing_percentage')->nullable();
            $table->time('available_from')->nullable();
            $table->time('available_to')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quizzes');
    }
};
