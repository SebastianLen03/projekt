<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupInvitationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('group_invitations', function (Blueprint $table) {
            $table->id(); // Klucz główny (ID zaproszenia)
            $table->foreignId('group_id')->constrained()->onDelete('cascade'); // Klucz obcy do tabeli `groups`
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Klucz obcy do tabeli `users`
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending'); // Status zaproszenia
            $table->timestamps(); // Znacznik czasu utworzenia oraz ostatniej aktualizacji zaproszenia
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('group_invitations');
    }
}
