<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateQuestionsTableNullableFields extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('questions', function (Blueprint $table) {
            // Zmiana kolumn na nullable, aby obsłużyć pytania otwarte
            $table->string('option_a')->nullable()->change();
            $table->string('option_b')->nullable()->change();
            $table->string('option_c')->nullable()->change();
            $table->string('option_d')->nullable()->change();
            $table->string('correct_option')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('questions', function (Blueprint $table) {
            // Przywracanie stanu pierwotnego, jeśli będziesz musiał wycofać migrację
            $table->string('option_a')->nullable(false)->change();
            $table->string('option_b')->nullable(false)->change();
            $table->string('option_c')->nullable(false)->change();
            $table->string('option_d')->nullable(false)->change();
            $table->string('correct_option')->nullable(false)->change();
        });
    }
}

