<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('report_uploads', function (Blueprint $table) {
            $table->renameColumn('file', 'file_path');
            $table->renameColumn('path', 'file_url');
        });

        Schema::table('report_uploads', function (Blueprint $table) {
            $table->string('token')->nullable()->change();
            $table->string('type')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('report_uploads', function (Blueprint $table) {
            $table->renameColumn('file_path', 'file');
            $table->renameColumn('file_url', 'path');
        });

        Schema::table('report_uploads', function (Blueprint $table) {
            $table->string('token')->nullable(false)->change();
            $table->string('type')->nullable(false)->change();
        });
    }
};
