<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('branches')) {
            return;
        }

        $mainBranchId = DB::table('branches')->where('is_main', true)->value('id')
            ?: DB::table('branches')->orderBy('id')->value('id');

        if (!$mainBranchId) {
            return;
        }

        if (Schema::hasTable('branch_module_settings')
            && Schema::hasColumn('branch_module_settings', 'default_branch_id')) {
            DB::table('branch_module_settings')
                ->whereNull('default_branch_id')
                ->update([
                    'default_branch_id' => $mainBranchId,
                    'updated_at' => now(),
                ]);
        }

        foreach ($this->businessTables() as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'branch_id')) {
                continue;
            }

            DB::table($table)
                ->whereNull('branch_id')
                ->update(['branch_id' => $mainBranchId]);
        }
    }

    public function down(): void
    {
        // Forward-only: assigned ownership must not be converted back to NULL.
    }

    private function businessTables(): array
    {
        return [
            'articles',
            'attendances',
            'bank_accounts',
            'bilties',
            'c_r_s',
            'cargos',
            'customer_payments',
            'customers',
            'd_r_s',
            'daily_ledger_deposits',
            'daily_ledger_uses',
            'employee_payments',
            'employees',
            'expenses',
            'fabrics',
            'inventory_items',
            'inventory_transactions',
            'invoices',
            'issued_fabrics',
            'orders',
            'payment_programs',
            'physical_quantities',
            'production_flows',
            'production_materials',
            'production_tags',
            'productions',
            'rates',
            'return_fabrics',
            'salaries',
            'sales_returns',
            'setups',
            'shipments',
            'statement_adjustments',
            'supplier_payments',
            'suppliers',
            'users',
            'utility_accounts',
            'utility_bills',
            'vouchers',
        ];
    }
};
