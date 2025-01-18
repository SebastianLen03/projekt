<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateQuizVersionsTable extends Migration
{
    public function up()
    {
        Schema::create('quiz_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->integer('version_number');
            $table->boolean('is_draft')->default(false);
            $table->boolean('has_passing_criteria')->default(false);
            $table->integer('passing_score')->nullable();
            $table->integer('passing_percentage')->nullable();
            $table->integer('time_limit')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('quiz_versions');
    }
}
