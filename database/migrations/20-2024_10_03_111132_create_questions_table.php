<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migration file for the questions table
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->string('question_text');
            $table->enum('type', ['open', 'single_choice', 'multiple_choice']);
            $table->integer('points')->default(1);
            $table->enum('points_type', ['full', 'partial'])
                  ->default('full')
                  ->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('questions');
    }
};
