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
        Schema::table('locacaos', function (Blueprint $table) {
            $table->string('assinafy_document_id')->nullable()->after('obs');
            $table->string('assinafy_status')->nullable()->after('assinafy_document_id');
            // pending | sent | signed | refused
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('locacoes', function (Blueprint $table) {
            //
        });
    }
};
