<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('report_uploads', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_id')->nullable()->after('id');
            $table->boolean('is_folder')->default(false)->after('type');
            $table->boolean('is_hidden')->default(false)->after('is_folder');
            $table->bigInteger('size')->nullable()->after('is_hidden');
            $table->foreign('parent_id')->references('id')->on('report_uploads')->onDelete('cascade');
            $table->index('parent_id');
            $table->index('is_folder');
        });
    }

    public function down(): void
    {
        Schema::table('report_uploads', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_folder']);
            $table->dropColumn(['parent_id', 'is_folder', 'is_hidden', 'size']);
        });
    }
};
