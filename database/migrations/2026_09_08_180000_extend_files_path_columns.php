<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * `file` und `name` waren durch Builder::defaultStringLength(191) auf
     * varchar(191) begrenzt. Aus Mail-Betreffs erzeugte Dateinamen sind
     * regelmaessig laenger und liefen in SQLSTATE[22001].
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('file', 1024)->change();
            $table->string('name', 512)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->string('file', 191)->change();
            $table->string('name', 191)->change();
        });
    }
};
