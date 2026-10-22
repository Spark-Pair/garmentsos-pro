<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sales_returns', 'return_no')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->string('return_no')->nullable()->after('id')->index();
            });
        }

        // Legacy rows were stored one line at a time. Keep their data intact,
        // but give every calendar date one stable document number so the old
        // records open as documents too.
        DB::table('sales_returns')
            ->whereNull('return_no')
            ->orderBy('date')
            ->orderBy('id')
            ->select(['id', 'date'])
            ->get()
            ->groupBy(fn ($row) => (string) $row->date)
            ->each(function ($rows, $date) {
                $dateKey = preg_replace('/[^0-9]/', '', $date);
                $returnNo = 'LEGACY-SR-' . $dateKey;

                DB::table('sales_returns')
                    ->whereIn('id', $rows->pluck('id')->all())
                    ->whereNull('return_no')
                    ->update(['return_no' => $returnNo]);

                if (Schema::hasTable('customer_payments')) {
                    DB::table('customer_payments')
                        ->where('type', 'sales_return')
                        ->whereDate('date', $date)
                        ->update(['reff_no' => $returnNo]);
                }
            });
    }

    public function down(): void
    {
        if (Schema::hasColumn('sales_returns', 'return_no')) {
            Schema::table('sales_returns', function (Blueprint $table) {
                $table->dropColumn('return_no');
            });
        }
    }
};
