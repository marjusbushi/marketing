<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $t) {
            $t->foreignId('claimed_by_user_id')
                ->nullable()
                ->after('assigned_to')
                ->constrained('users')
                ->nullOnDelete();
            $t->timestamp('claimed_at')->nullable()->after('claimed_by_user_id');
            $t->index(['stage', 'claimed_by_user_id'], 'dbp_stage_claim_idx');
        });
    }

    public function down(): void
    {
        Schema::table('daily_basket_posts', function (Blueprint $t) {
            $t->dropIndex('dbp_stage_claim_idx');
            $t->dropConstrainedForeignId('claimed_by_user_id');
            $t->dropColumn('claimed_at');
        });
    }
};
