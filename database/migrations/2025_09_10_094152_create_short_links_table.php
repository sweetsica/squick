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
        Schema::create('short_links', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('title')->nullable();
            $table->string('thumbnail')->nullable();
            $table->string('type')->nullable();
            $table->string('url')->nullable();
            $table->string('shortUrl')->nullable();
            $table->string('path')->nullable();
            $table->string('extend')->nullable();
            $table->string('size')->nullable();
            $table->string('status')->default(1);
            $table->json('userAccess')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('short_links');
    }
};
