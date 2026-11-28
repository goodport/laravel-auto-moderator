<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_moderator_runs', function (Blueprint $table): void {
            $table->json('field_labels')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('auto_moderator_runs', function (Blueprint $table): void {
            $table->dropColumn('field_labels');
        });
    }
};
