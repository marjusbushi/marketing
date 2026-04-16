<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pivot: daily_basket_post ↔ DIS item_group.
 *
 * A single post may feature multiple products (e.g. an outfit reel with
 * 3 item groups). Products live in DIS, so item_group_id is a cross-DB
 * unsignedBigInteger with an index — no FK constraint.
 *
 * `is_hero` marks the primary product for the post (the one whose image
 * goes on the card thumbnail; used when a post has many products).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_basket_post_products', function (Blueprint $table) {
            $table->id();

            $table->foreignId('daily_basket_post_id')
                ->constrained('daily_basket_posts')
                ->cascadeOnDelete();

            // Cross-DB reference to DIS item_groups
            $table->unsignedBigInteger('item_group_id')->index();

            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_hero')->default(false);

            $table->timestamps();

            // A product appears at most once per post
            $table->unique(
                ['daily_basket_post_id', 'item_group_id'],
                'daily_basket_post_products_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_basket_post_products');
    }
};
