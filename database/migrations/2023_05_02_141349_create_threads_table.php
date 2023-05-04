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
        Schema::create('threads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('thread_id')->unique()->index();
            $table->foreignId('author_id')->nullable();
            $table->unsignedBigInteger('author_twitter_id')->index();
            $table->string('snippet', 3000)->collation('utf8mb4_bin');
            $table->longText('content')->collation('utf8mb4_bin');
            $table->integer('count')->nullable();
            $table->string('hashtags', 1000)->nullable();
            $table->string('language', 100)->nullable();
            $table->unsignedBigInteger('views')->default(0)->nullable();
            $table->timestamp('thread_created_at')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('threads');
    }
};
