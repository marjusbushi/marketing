<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links a marketing template to a Canva brand template.
 *
 * Post-pivot (Decision #14), the photo/carousel/story path is served by
 * Canva Connect instead of an embedded editor. A template row now carries
 * an optional `canva_brand_template_id` — when present, the Visual Studio
 * "Open in Canva" button uses that id to autofill a new Canva design
 * with the brand kit.
 *
 * The column is nullable so legacy Polotno entries remain valid until
 * they are either migrated or removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('marketing_templates', function (Blueprint $table) {
            $table->string('canva_brand_template_id', 120)
                ->nullable()
                ->after('source')
                ->index();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_templates', function (Blueprint $table) {
            $table->dropIndex(['canva_brand_template_id']);
            $table->dropColumn('canva_brand_template_id');
        });
    }
};
