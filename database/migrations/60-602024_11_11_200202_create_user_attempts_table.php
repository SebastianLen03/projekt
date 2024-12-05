<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserAttemptsTable extends Migration
{
    public function up()
    {
        Schema::create('user_attempts', function (Blueprint $table) {
            $table->id(); // unsignedBigInteger('id') AUTO_INCREMENT PRIMARY KEY
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_id')->constrained()->onDelete('cascade');
            $table->foreignId('quiz_version_id')->constrained('quiz_versions')->onDelete('cascade'); // Usunięcie nullable()
            $table->integer('attempt_number');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->integer('total_score')->nullable();
            $table->integer('score')->default(0);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('user_attempts');
    }
}
