<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing AI call audit log.
 *
 * One row per call to Claude via AIContentService. Captures enough to
 * debug prompt behaviour, measure cost per user / per endpoint, and
 * feed the Faza 2 prompt-eval workflow. Prompts are hashed (SHA-256)
 * rather than stored verbatim to keep the table small and avoid PII.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_ai_calls', function (Blueprint $table) {
            $table->id();

            // Cross-DB: DIS users
            $table->unsignedBigInteger('user_id')->nullable();

            $table->string('endpoint', 60);                  // caption | rewrite | draft-package (L2)
            $table->string('model', 60);
            $table->string('prompt_hash', 64);               // SHA-256
            $table->unsignedInteger('tokens_in')->nullable();
            $table->unsignedInteger('tokens_out')->nullable();
            $table->unsignedInteger('cost_cents')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->boolean('ok')->default(true);
            $table->string('error_code', 60)->nullable();

            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['endpoint', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_ai_calls');
    }
};
