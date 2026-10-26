<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_returns')
            ->where('return_no', 'like', 'LEGACY-SR-%')
            ->get(['id', 'return_no'])
            ->each(function ($row) {
                $normalized = preg_replace('/^(LEGACY-SR-[0-9]{8})[0-9]{6}$/', '$1', (string) $row->return_no);
                if ($normalized && $normalized !== $row->return_no) {
                    DB::table('sales_returns')->where('id', $row->id)->update(['return_no' => $normalized]);
                }
            });

        DB::table('customer_payments')
            ->where('type', 'sales_return')
            ->where('reff_no', 'like', 'LEGACY-SR-%')
            ->get(['id', 'reff_no'])
            ->each(function ($row) {
                $normalized = preg_replace('/^(LEGACY-SR-[0-9]{8})[0-9]{6}$/', '$1', (string) $row->reff_no);
                if ($normalized && $normalized !== $row->reff_no) {
                    DB::table('customer_payments')->where('id', $row->id)->update(['reff_no' => $normalized]);
                }
            });
    }

    public function down(): void
    {
        // Deliberately keep normalized document numbers; reverting them would
        // recreate the malformed legacy references this repair removed.
    }
};
