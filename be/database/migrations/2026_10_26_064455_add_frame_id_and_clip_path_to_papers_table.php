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
        Schema::table('papers', function (Blueprint $table) {
            $table->string('clip_path')->nullable()->after('file_path');
            $table->foreignId('frame_id')->nullable()->after('ref')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('papers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('frame_id');
            $table->dropColumn('clip_path');
        });
    }
};
