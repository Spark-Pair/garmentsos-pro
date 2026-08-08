<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        $hasCottonCount = Schema::hasColumn('invoices', 'cotton_count');
        $hasCartonCount = Schema::hasColumn('invoices', 'carton_count');

        if ($hasCottonCount && !$hasCartonCount) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('cotton_count', 'carton_count');
            });

            return;
        }

        if (!$hasCartonCount) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->integer('carton_count')->nullable()->after('customer_id');
            });
        }

        if ($hasCottonCount) {
            DB::table('invoices')
                ->whereNull('carton_count')
                ->update(['carton_count' => DB::raw('cotton_count')]);

            Schema::table('invoices', function (Blueprint $table) {
                $table->dropColumn('cotton_count');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('invoices')) {
            return;
        }

        $hasCartonCount = Schema::hasColumn('invoices', 'carton_count');
        $hasCottonCount = Schema::hasColumn('invoices', 'cotton_count');

        if ($hasCartonCount && !$hasCottonCount) {
            Schema::table('invoices', function (Blueprint $table) {
                $table->renameColumn('carton_count', 'cotton_count');
            });
        }
    }
};
