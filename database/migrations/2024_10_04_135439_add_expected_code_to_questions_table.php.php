<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddExpectedCodeToQuestionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Sprawdzamy, czy kolumna 'expected_code' już nie istnieje
        if (!Schema::hasColumn('questions', 'expected_code')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->text('expected_code')->nullable();  // Dodanie kolumny expected_code
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Usuwanie kolumny expected_code podczas rollbacku
        if (Schema::hasColumn('questions', 'expected_code')) {
            Schema::table('questions', function (Blueprint $table) {
                $table->dropColumn('expected_code');
            });
        }
    }
}
