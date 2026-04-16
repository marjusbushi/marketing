<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consolidated Content Planner schema.
 *
 * User columns are unsignedBigInteger + index (no FK constraint)
 * because users live in the DIS database. The Eloquent relationship
 * resolves cross-DB via User model's $connection = 'dis'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── Campaigns ────────────────────────────────────────
        Schema::create('content_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('color', 7)->default('#6366f1');
            $table->text('description')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Posts ─────────────────────────────────────────────
        Schema::create('content_posts', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('campaign_id')->nullable()->index();
            $table->string('platform', 20)->default('multi');
            $table->text('content')->nullable();
            $table->dateTime('scheduled_at')->nullable()->index();
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->string('platform_post_id')->nullable();
            $table->string('permalink')->nullable();
            $table->string('approval_type', 20)->default('none');
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->dateTime('approved_at')->nullable();
            $table->timestamp('approval_locked_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('external_source', 30)->nullable();
            $table->string('external_post_id')->nullable();
            $table->string('meta_post_type', 20)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'scheduled_at']);
            $table->index(['platform', 'status']);
            $table->unique(['platform', 'platform_post_id'], 'content_posts_platform_post_unique');
            $table->index(['external_source', 'external_post_id']);
        });

        // ── Post Platforms ────────────────────────────────────
        Schema::create('content_post_platforms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_post_id')->constrained('content_posts')->cascadeOnDelete();
            $table->string('platform', 20);
            $table->text('platform_content')->nullable();
            $table->string('platform_post_id')->nullable();
            $table->dateTime('published_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['content_post_id', 'platform']);
        });

        // ── Media ─────────────────────────────────────────────
        Schema::create('content_media', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('disk', 20)->default('r2_cdn');
            $table->string('path');
            $table->string('mime_type', 100)->index();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('thumbnail_path')->nullable();
            $table->string('alt_text')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        // ── Post ↔ Media pivot ────────────────────────────────
        Schema::create('content_post_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_post_id')->constrained('content_posts')->cascadeOnDelete();
            $table->foreignId('content_media_id')->constrained('content_media')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('created_at')->nullable();

            $table->unique(['content_post_id', 'content_media_id']);
        });

        // ── Labels ────────────────────────────────────────────
        Schema::create('content_labels', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50);
            $table->string('color', 7)->default('#6366f1');
            $table->timestamps();
        });

        // ── Post ↔ Labels pivot ───────────────────────────────
        Schema::create('content_post_labels', function (Blueprint $table) {
            $table->foreignId('content_post_id')->constrained('content_posts')->cascadeOnDelete();
            $table->foreignId('content_label_id')->constrained('content_labels')->cascadeOnDelete();

            $table->primary(['content_post_id', 'content_label_id']);
        });

        // ── Comments ──────────────────────────────────────────
        Schema::create('content_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_post_id')->constrained('content_posts')->cascadeOnDelete()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('guest_name', 100)->nullable();
            $table->text('body');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_internal')->default(true);
            $table->dateTime('resolved_at')->nullable();
            $table->string('external_id', 255)->nullable()->index();
            $table->string('external_platform', 20)->nullable();
            $table->string('external_author', 255)->nullable();
            $table->string('external_author_avatar', 500)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('parent_id')->references('id')->on('content_comments')->cascadeOnDelete();
        });

        // ── Approval Steps ────────────────────────────────────
        Schema::create('content_approval_steps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedTinyInteger('step_order');
            $table->string('role', 50)->nullable();
            $table->unsignedBigInteger('assigned_to')->nullable()->index();
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->unsignedBigInteger('acted_by')->nullable();
            $table->timestamp('acted_at')->nullable();
            $table->text('feedback')->nullable();
            $table->timestamps();

            $table->foreign('post_id', 'approval_steps_post_fk')->references('id')->on('content_posts')->cascadeOnDelete();
            $table->unique(['post_id', 'step_order']);
            $table->index(['assigned_to', 'status']);
        });

        // ── Post Versions ─────────────────────────────────────
        Schema::create('content_post_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id')->index();
            $table->unsignedInteger('version_number');
            $table->json('snapshot');
            $table->string('change_summary', 255)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->foreign('post_id', 'post_versions_post_fk')->references('id')->on('content_posts')->cascadeOnDelete();
            $table->unique(['post_id', 'version_number']);
        });

        // ── Suggestions ───────────────────────────────────────
        Schema::create('content_suggestions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('post_id');
            $table->unsignedBigInteger('user_id')->index();
            $table->text('original_text');
            $table->text('suggested_text');
            $table->unsignedInteger('position_start')->nullable();
            $table->unsignedInteger('position_end')->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->unsignedBigInteger('resolved_by')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->foreign('post_id', 'suggestions_post_fk')->references('id')->on('content_posts')->cascadeOnDelete();
            $table->index(['post_id', 'status']);
        });

        // ── Share Links ───────────────────────────────────────
        Schema::create('content_share_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 64)->unique();
            $table->morphs('shareable');
            $table->unsignedBigInteger('created_by')->index();
            $table->enum('permission', ['view', 'comment', 'approve'])->default('view');
            $table->string('password_hash')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['token', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_share_links');
        Schema::dropIfExists('content_suggestions');
        Schema::dropIfExists('content_post_versions');
        Schema::dropIfExists('content_approval_steps');
        Schema::dropIfExists('content_comments');
        Schema::dropIfExists('content_post_labels');
        Schema::dropIfExists('content_labels');
        Schema::dropIfExists('content_post_media');
        Schema::dropIfExists('content_media');
        Schema::dropIfExists('content_post_platforms');
        Schema::dropIfExists('content_posts');
        Schema::dropIfExists('content_campaigns');
    }
};
