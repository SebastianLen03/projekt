<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersionedAnswersTable extends Migration
{
    public function up()
    {
        Schema::create('versioned_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versioned_question_id')->constrained()->onDelete('cascade');
            $table->string('text')->nullable();
            $table->boolean('is_correct')->nullable();
            $table->text('expected_code')->nullable();
            $table->string('language', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('versioned_answers');
    }
}
