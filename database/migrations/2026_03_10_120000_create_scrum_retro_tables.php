<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scrum_retro_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique();
            $table->string('host_token', 80)->unique();
            $table->string('team_name')->nullable();
            $table->string('board_title')->nullable();
            $table->string('theme', 32)->default('momentum');
            $table->json('areas');
            $table->timestamps();

            $table->index('code');
        });

        Schema::create('scrum_retro_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('scrum_retro_sessions')->onDelete('cascade');
            $table->string('name');
            $table->string('token', 80)->unique();
            $table->string('role', 32)->default('participant');
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'name']);
        });

        Schema::create('scrum_retro_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('session_id')->constrained('scrum_retro_sessions')->onDelete('cascade');
            $table->foreignId('participant_id')->constrained('scrum_retro_participants')->onDelete('cascade');
            $table->string('area_key', 40);
            $table->text('text');
            $table->string('color', 32)->nullable();
            $table->timestamps();

            $table->index(['session_id', 'area_key']);
            $table->index(['session_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scrum_retro_items');
        Schema::dropIfExists('scrum_retro_participants');
        Schema::dropIfExists('scrum_retro_sessions');
    }
};
