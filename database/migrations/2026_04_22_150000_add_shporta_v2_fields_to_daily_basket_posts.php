<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shporta Ditore v2 — post-first layout.
 *
 * Adds three free-text context fields used by the redesigned Post Detail
 * view (3-column layout: BURIMI / PUBLIKIMI / KONTENTI):
 *
 *   • lokacioni — where the shoot/capture happens (e.g. "Dyqani Tirana Center")
 *   • modelet   — which models/talent appear (e.g. "Era, Bora")
 *   • audienca  — target audience note (e.g. "gra 25-34, urbane")
 *
 * reference_url / reference_notes / stage / priority / target_platforms /
 * caption / hashtags / title already live on the base table — this migration
 * only appends the three new fields.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $table) {
            $table->string('lokacioni', 255)->nullable()->after('hashtags');
            $table->string('modelet', 255)->nullable()->after('lokacioni');
            $table->string('audienca', 255)->nullable()->after('modelet');
        });
    }

    public function down(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $table) {
            $table->dropColumn(['lokacioni', 'modelet', 'audienca']);
        });
    }
};
