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
        Schema::create('authors', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('twitter_id')->unique()->index();
            $table->string('username', 100)->index();
            $table->string('name', 150)->collation('utf8mb4_bin');
            $table->string('bio', 1000)->collation('utf8mb4_bin')->nullable();
            $table->string('profile_picture', 500)->nullable();
            $table->boolean('verified')->nullable();
            $table->string('sponsor_platform', 250)->nullable();
            $table->string('sponsor_url', 500)->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};
