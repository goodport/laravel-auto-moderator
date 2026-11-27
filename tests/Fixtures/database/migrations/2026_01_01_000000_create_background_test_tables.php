<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('moderatable_subjects', function (Blueprint $table): void {
            $table->id();
            $table->string('scenario');
            $table->json('fields')->nullable();
            $table->string('language')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable();
            $table->timestamps();
        });

        Schema::create('subjects', function (Blueprint $table): void {
            $table->id();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subjects');
        Schema::dropIfExists('moderatable_subjects');
    }
};
