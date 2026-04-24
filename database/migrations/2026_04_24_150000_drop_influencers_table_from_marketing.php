<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drop the duplicate marketing-side influencers table.
 *
 * Original migration (2026_04_13_200003_create_influencers_table)
 * created a marketing-owned copy. After moving to DIS-as-source-of-truth
 * for influencer profiles (so influencer_products.influencer_id stays
 * valid and avoids cross-DB drift), Flare reads via DisInfluencer
 * (connection='dis') and writes through DisApiClient → DIS internal API.
 * The marketing-side table is no longer referenced by any code.
 *
 * This migration is safe: the table has no data in any Flare environment
 * (influencer CRUD was broken end-to-end before the recent fixes).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('influencers');
    }

    public function down(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->index();
            $table->string('platform', 30)->default('instagram');
            $table->string('handle', 255)->nullable();
            $table->string('phone', 50)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
