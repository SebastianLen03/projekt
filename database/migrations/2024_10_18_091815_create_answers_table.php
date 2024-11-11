<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnswersTable extends Migration
{
    public function up()
    {
        Schema::create('answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->onDelete('cascade');
            $table->string('text')->nullable(); // Używane dla pytań zamkniętych, null dla otwartych
            $table->boolean('is_correct')->nullable(); // null dla pytań otwartych
            $table->text('expected_code')->nullable(); // Używane dla pytań otwartych, null dla zamkniętych
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('answers');
    }
}
