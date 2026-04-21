<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marketing Brand Kit — singleton table (1 row ever).
 *
 * Holds the canonical brand identity used by every Visual Studio component:
 *   • Polotno reads colors/typography/logos to style the photo editor
 *   • Remotion templates read the same JSON for consistent video branding
 *   • Claude AI reads voice_sq/voice_en + caption_templates to generate copy
 *   • Music library is picked by the AI in Faza 2 (AI Smart)
 *
 * Cached 60s in BrandKitService — every request read hits Redis, not MySQL.
 *
 * All visual/creative fields are JSON because they evolve faster than schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_brand_kit', function (Blueprint $table) {
            $table->id();

            $table->json('colors')->nullable();
            $table->json('typography')->nullable();
            $table->json('logo_variants')->nullable();
            $table->json('watermark')->nullable();

            $table->text('voice_sq')->nullable();
            $table->text('voice_en')->nullable();

            $table->json('caption_templates')->nullable();
            $table->json('default_hashtags')->nullable();
            $table->json('music_library')->nullable();
            $table->json('aspect_defaults')->nullable();

            // Cross-DB: DIS users. Bigint, no FK constraint.
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_brand_kit');
    }
};
