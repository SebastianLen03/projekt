<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupQuizTable extends Migration
{
    public function up()
    {
        Schema::create('group_quiz', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('quiz_id');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');

            $table->unique(['group_id', 'quiz_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_quiz');
    }
}
