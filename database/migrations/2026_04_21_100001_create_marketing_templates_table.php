<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Templates — reusable starting points for Creative Briefs.
 *
 * A template is either a Polotno JSON document (photo/carousel/story-static)
 * or a reference to a Remotion composition (reel/video). The `engine` column
 * decides which editor opens when a user creates a brief from this template.
 *
 * `metadata` is consumed by Claude in Faza 2 to auto-pick templates based on
 * product type, use case, aspect, duration etc.
 *
 * Seed set (~5–8) is loaded via MarketingTemplatesSeeder and marked
 * is_system=true so users cannot delete them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_templates', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);
            $table->string('slug', 120)->unique();

            // photo | carousel | reel | video | story
            $table->string('kind', 20);

            // polotno | remotion
            $table->string('engine', 20);

            // Polotno JSON state OR Remotion composition reference
            $table->json('source');

            // Metadata for AI: {use_case, fits_products, aspect, duration, notes}
            $table->json('metadata')->nullable();

            $table->string('thumbnail_path', 500)->nullable();

            $table->boolean('is_system')->default(false);
            $table->boolean('is_active')->default(true);

            // Cross-DB: DIS users
            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();

            $table->index(['kind', 'is_active']);
            $table->index('engine');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_templates');
    }
};
