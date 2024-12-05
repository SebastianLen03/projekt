<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersionedQuestionsTable extends Migration
{
    public function up()
    {
        Schema::create('versioned_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_version_id')->constrained()->onDelete('cascade');
            $table->string('question_text');
            $table->enum('type', ['open', 'single_choice', 'multiple_choice']);
            $table->integer('points')->default(1);
            $table->enum('points_type', ['full', 'partial'])->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('versioned_questions');
    }
}
