<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Canva Connections — per-user OAuth tokens for Canva Connect API.
 *
 * One row per marketing user who has linked their Canva account. Tokens are
 * encrypted at rest via the `encrypted` cast on the CanvaConnection model;
 * rotation of APP_KEY invalidates stored tokens (users re-authenticate).
 *
 * user_id references the DIS users table — no FK because the users table
 * lives on a separate database connection.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_canva_connections', function (Blueprint $table) {
            $table->id();

            // DIS users — cross-DB reference, no FK.
            $table->unsignedBigInteger('user_id')->unique();

            // Opaque Canva user id (UUID-like string). Populated on callback.
            $table->string('canva_user_id')->nullable()->index();
            $table->string('canva_display_name')->nullable();

            // Encrypted blobs — cast to `encrypted` on the model.
            $table->text('access_token');
            $table->text('refresh_token');

            $table->json('scopes')->nullable();

            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('connected_at')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_canva_connections');
    }
};
