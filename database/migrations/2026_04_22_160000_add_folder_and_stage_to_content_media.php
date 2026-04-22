<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Media Library v2 — foldera + stage.
 *
 *   • folder — null ose një nga: reels, videos, photos, stories, referenca, imported
 *   • stage  — raw (default), edited, final
 *
 * Backfill: media nga Meta import (path LIKE content-planner/meta-imports/%)
 * klasifikohen si folder=imported + stage=final. Pjesa tjetër mbetet folder=NULL
 * (auto-klasifikohet kur preket nga service) + stage=raw.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->string('folder', 64)->nullable()->after('alt_text');
            $table->string('stage', 16)->default('raw')->after('folder');

            $table->index('folder');
            $table->index('stage');
        });

        DB::table('content_media')
            ->where('path', 'like', 'content-planner/meta-imports/%')
            ->update([
                'folder' => 'imported',
                'stage' => 'final',
            ]);
    }

    public function down(): void
    {
        Schema::table('content_media', function (Blueprint $table) {
            $table->dropIndex(['folder']);
            $table->dropIndex(['stage']);
            $table->dropColumn(['folder', 'stage']);
        });
    }
};
