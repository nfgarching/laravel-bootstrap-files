<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('files', function (Blueprint $table) {
            $table->id();
            $table->uuid()->nullable()->unique();

            $table->morphs('model');

            $table->string('collection_name');
            $table->json('metadata')->nullable();
            $table->string('state');  // z. B. "active", "error", "verified", "unverified", "pending"
            $table->string('type');   // z. B. "image", "pdf", "document", "video", "audio"

            $table->string('disk');
            $table->string('file', 1024);
            $table->string('hash')->nullable();
            $table->string('mime')->nullable();
            $table->string('name', 512);
            // $table->integer('node')->nullable()->index('node');
            $table->unsignedBigInteger('size');

            $table->string('conversions_disk')->nullable();
            $table->json('manipulations')->nullable();
            $table->json('custom_properties')->nullable();
            $table->json('generated_conversions')->nullable();
            $table->json('responsive_images')->nullable();
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->text('memo')->nullable();
            $table->timestamps();
        });

        Schema::create('file_props', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_id')
                ->constrained('files')
                ->onDelete('cascade'); // One-to-Many Beziehung
            $table->string('key')->nullable();   // Property key/name
            $table->string('type')->nullable();  // z. B. "birth_date", "death_date", "iban"
            $table->string('label')->nullable(); // z.B. "Privat", "Geschäftlich"
            $table->string('value')->nullable(); // eigentliche Nummer oder Adresse (changed to text for longer values)
            $table->json('data')->nullable();    // JSON-Feld für große Daten z.B. ...
            $table->text('memo')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('files');
    }
};
