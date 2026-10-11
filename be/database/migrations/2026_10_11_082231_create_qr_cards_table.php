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
        Schema::create('qr_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('deck');
            $table->unsignedTinyInteger('number');
            $table->uuid('uuid')->unique();
            $table->timestamps();

            $table->unique(['deck', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qr_cards');
    }
};
