<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->foreignId('answer_id')->nullable()->constrained('answers')->onDelete('cascade');
            $table->integer('attempt_id')->default(1);
            $table->text('open_answer')->nullable(); // Tekst odpowiedzi (dla pytań otwartych)
            $table->boolean('is_correct')->default(false); // Czy odpowiedź jest poprawna
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_answers');
    }
};
