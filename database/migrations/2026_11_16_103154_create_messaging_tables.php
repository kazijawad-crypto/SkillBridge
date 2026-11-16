<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('context_type', 20);
            $table->unsignedBigInteger('context_id');
            $table->foreignId('user_one_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('user_two_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'context_type', 'context_id', 'user_one_id', 'user_two_id'], 'conversation_context_participants_unique');
        });
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->index(['conversation_id', 'id']);
        });
        Schema::create('notification_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('messages')->default(true);
            $table->boolean('proposals')->default(true);
            $table->boolean('task_assignments')->default(true);
            $table->boolean('review_requests')->default(true);
            $table->boolean('deadlines')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
