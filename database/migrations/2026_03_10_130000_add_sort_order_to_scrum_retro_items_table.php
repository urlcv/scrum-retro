<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('scrum_retro_items', 'sort_order')) {
            Schema::table('scrum_retro_items', function (Blueprint $table): void {
                $table->unsignedInteger('sort_order')->default(0)->after('area_key');
                $table->index(['session_id', 'area_key', 'sort_order'], 'scrum_retro_items_lane_sort_idx');
            });
        }

        DB::table('scrum_retro_items')
            ->where('sort_order', 0)
            ->update(['sort_order' => DB::raw('id')]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('scrum_retro_items', 'sort_order')) {
            Schema::table('scrum_retro_items', function (Blueprint $table): void {
                $table->dropIndex('scrum_retro_items_lane_sort_idx');
                $table->dropColumn('sort_order');
            });
        }
    }
};
