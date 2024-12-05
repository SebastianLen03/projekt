<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAnswersTable extends Migration
{
    public function up()
    {
        Schema::create('user_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // Poprawa attempt_id
            $table->foreignId('attempt_id')->constrained('user_attempts')->onDelete('cascade');

            // Kolumny wersjonowane
            $table->foreignId('quiz_version_id')->constrained('quiz_versions')->onDelete('cascade');
            $table->foreignId('versioned_question_id')->constrained('versioned_questions')->onDelete('cascade');
            $table->foreignId('versioned_answer_id')->nullable()->constrained('versioned_answers')->onDelete('cascade');
            $table->text('selected_answers')->nullable();

            $table->boolean('is_manual_score')->default(false);
            // Inne pola
            $table->text('open_answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->integer('score')->default(0);

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_answers');
    }
}
