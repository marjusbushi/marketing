<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Add the two columns the publish pipeline needs to be safe under
     * concurrent workers and reschedules.
     *
     *   scheduled_at_version — bumped every time scheduled_at moves. Each
     *   PublishContentPostJob captures the version it was dispatched with;
     *   at fire time it runs an atomic UPDATE that only succeeds if the
     *   stored version still matches. A reschedule (which dispatches a new
     *   job and bumps the version) silently retires the old job; two
     *   parallel workers can only ever claim the row once.
     *
     *   error_message — surfaces the last publish failure on the post itself,
     *   not just on the per-platform row. The Content Planner UI uses this
     *   for the failure banner + "Riprovo" affordance.
     */
    public function up(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->unsignedInteger('scheduled_at_version')->default(0)->after('scheduled_at');
            $table->text('error_message')->nullable()->after('permalink');
        });
    }

    public function down(): void
    {
        Schema::table('content_posts', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at_version', 'error_message']);
        });
    }
};
