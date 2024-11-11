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
            $table->foreignId('answer_id')->nullable()->constrained('answers')->onDelete('cascade'); // ID wybranej odpowiedzi (dla pytań zamkniętych)
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
