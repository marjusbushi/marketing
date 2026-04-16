<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Influencer profiles — marketing-owned.
 *
 * Only the base `influencers` table lives here.
 * influencer_products and influencer_product_items stay in DIS
 * (they reference branches, warehouses, transfer_orders, invoices, items).
 * Those are accessed via DIS integration layer (task #986).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('influencers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255)->index();
            $table->string('platform', 30)->default('instagram'); // instagram, tiktok, youtube, other
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

    public function down(): void
    {
        Schema::dropIfExists('influencers');
    }
};
