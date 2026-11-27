<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auto_moderator_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('moderatable_type');
            $table->string('moderatable_id');
            $table->string('scenario');
            $table->string('status', 32);
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->dateTime('started_at')->nullable();
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();

            $table->index(['moderatable_type', 'moderatable_id']);
            $table->index('status');
            $table->index('scenario');
        });

        Schema::create('auto_moderator_results', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('moderation_run_id')
                ->constrained('auto_moderator_runs')
                ->cascadeOnDelete();
            $table->string('field_key');
            $table->string('check_key');
            $table->string('state', 16);
            $table->text('reason')->nullable();
            $table->text('error')->nullable();
            $table->json('findings')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_moderator_results');
        Schema::dropIfExists('auto_moderator_runs');
    }
};
