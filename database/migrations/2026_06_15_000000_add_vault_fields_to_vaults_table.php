<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vaults', function (Blueprint $table) {
            if (!Schema::hasColumn('vaults', 'original_name')) {
                $table->string('original_name')->after('name')->nullable();
            }
            if (!Schema::hasColumn('vaults', 'parent_id')) {
                $table->unsignedBigInteger('parent_id')->after('id')->nullable();
            }
            if (!Schema::hasColumn('vaults', 'is_folder')) {
                $table->boolean('is_folder')->default(false)->after('type');
            }
            if (!Schema::hasColumn('vaults', 'is_hidden')) {
                $table->boolean('is_hidden')->default(false)->after('is_folder');
            }
            if (!Schema::hasColumn('vaults', 'size')) {
                $table->bigInteger('size')->nullable()->after('is_hidden');
            }

            if (!Schema::hasColumn('vaults', 'parent_id')) {
                $table->foreign('parent_id')->references('id')->on('vaults')->onDelete('cascade');
            }
            if (!Schema::hasIndex('vaults', 'vaults_parent_id_index')) {
                $table->index('parent_id');
            }
            if (!Schema::hasIndex('vaults', 'vaults_is_folder_index')) {
                $table->index('is_folder');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vaults', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropIndex(['parent_id']);
            $table->dropIndex(['is_folder']);
            $table->dropColumn(['parent_id', 'original_name', 'is_folder', 'is_hidden', 'size']);
        });
    }
};
