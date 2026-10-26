<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('sales_returns')
            ->whereNotNull('return_no')
            ->where('return_no', 'not like', 'LEGACY-SR-%')
            ->get(['id', 'return_no', 'branch_id'])
            ->each(function ($row) {
                if (!preg_match('/^\d+$/', (string) $row->return_no)) {
                    return;
                }

                $branch = $row->branch_id
                    ? DB::table('branches')->where('id', $row->branch_id)->first(['prefix', 'code'])
                    : null;
                $prefix = strtoupper(preg_replace('/[^A-Z0-9]+/', '', (string) ($branch?->prefix ?: $branch?->code ?: '')));
                $newNumber = ($prefix ? $prefix . '-' : '') . 'SR-' . $row->return_no;

                DB::table('sales_returns')
                    ->where('return_no', $row->return_no)
                    ->where('branch_id', $row->branch_id)
                    ->update(['return_no' => $newNumber]);

                DB::table('customer_payments')
                    ->where('type', 'sales_return')
                    ->where('reff_no', $row->return_no)
                    ->update(['reff_no' => $newNumber]);
            });
    }

    public function down(): void
    {
        // Keep corrected document numbers stable.
    }
};
