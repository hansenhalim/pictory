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
        Schema::create('papers', function (Blueprint $table) {
            $table->id();
            $table->uuid('ref')->unique();
            $table->foreignId('frame_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('qr_card_id')->nullable()->constrained();
            $table->foreignId('moment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_path');
            $table->string('clip_path')->nullable();
            $table->json('photos');
            $table->timestamps();
        });

        /*
         * A moment's cover is one of its papers, and papers belong to moments, so the cover's
         * constraint can only be added once both tables exist.
         */
        Schema::table('moments', function (Blueprint $table) {
            $table->foreign('cover_paper_id')->references('id')->on('papers')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('moments', function (Blueprint $table) {
            $table->dropForeign(['cover_paper_id']);
        });

        Schema::dropIfExists('papers');
    }
};
