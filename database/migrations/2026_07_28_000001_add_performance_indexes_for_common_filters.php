<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ($this->indexes() as $table => $indexes) {
            if (!Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $name => $columns) {
                $this->createIndexIfMissing($table, $name, $columns);
            }
        }
    }

    public function down(): void
    {
        // Forward-only performance hardening. Keeping indexes is safe for old data.
    }

    private function indexes(): array
    {
        return [
            'articles' => [
                'gos_articles_branch_date_id_idx' => ['branch_id', 'date', 'id'],
                'gos_articles_article_no_idx' => ['article_no'],
                'gos_articles_category_season_size_idx' => ['category', 'season', 'size'],
            ],
            'orders' => [
                'gos_orders_branch_id_idx' => ['branch_id', 'id'],
                'gos_orders_branch_date_idx' => ['branch_id', 'date'],
                'gos_orders_customer_date_idx' => ['customer_id', 'date'],
                'gos_orders_order_no_idx' => ['order_no'],
                'gos_orders_status_idx' => ['status'],
            ],
            'order_articles' => [
                'gos_order_articles_order_article_idx' => ['order_id', 'article_id'],
                'gos_order_articles_article_idx' => ['article_id'],
            ],
            'invoices' => [
                'gos_invoices_branch_id_idx' => ['branch_id', 'id'],
                'gos_invoices_branch_date_idx' => ['branch_id', 'date'],
                'gos_invoices_customer_date_idx' => ['customer_id', 'date'],
                'gos_invoices_invoice_no_idx' => ['invoice_no'],
                'gos_invoices_order_no_idx' => ['order_no'],
                'gos_invoices_shipment_no_idx' => ['shipment_no'],
            ],
            'invoice_articles' => [
                'gos_invoice_articles_invoice_article_idx' => ['invoice_id', 'article_id'],
                'gos_invoice_articles_article_idx' => ['article_id'],
            ],
            'shipments' => [
                'gos_shipments_branch_id_idx' => ['branch_id', 'id'],
                'gos_shipments_branch_date_idx' => ['branch_id', 'date'],
                'gos_shipments_shipment_no_idx' => ['shipment_no'],
                'gos_shipments_customer_idx' => ['customer_id'],
            ],
            'shipment_articles' => [
                'gos_shipment_articles_shipment_article_idx' => ['shipment_id', 'article_id'],
                'gos_shipment_articles_article_idx' => ['article_id'],
            ],
            'customer_payments' => [
                'gos_customer_payments_branch_id_idx' => ['branch_id', 'id'],
                'gos_customer_payments_customer_date_idx' => ['customer_id', 'date'],
                'gos_customer_payments_program_idx' => ['program_id'],
                'gos_customer_payments_bank_account_idx' => ['bank_account_id'],
                'gos_customer_payments_method_idx' => ['method'],
                'gos_customer_payments_type_idx' => ['type'],
                'gos_customer_payments_cheque_idx' => ['cheque_no'],
                'gos_customer_payments_slip_idx' => ['slip_no'],
                'gos_customer_payments_transaction_idx' => ['transaction_id'],
            ],
            'supplier_payments' => [
                'gos_supplier_payments_branch_id_idx' => ['branch_id', 'id'],
                'gos_supplier_payments_supplier_date_idx' => ['supplier_id', 'date'],
                'gos_supplier_payments_program_idx' => ['program_id'],
                'gos_supplier_payments_voucher_idx' => ['voucher_id'],
                'gos_supplier_payments_bank_account_idx' => ['bank_account_id'],
                'gos_supplier_payments_method_idx' => ['method'],
                'gos_supplier_payments_cheque_idx' => ['cheque_id'],
                'gos_supplier_payments_slip_idx' => ['slip_id'],
                'gos_supplier_payments_transaction_idx' => ['transaction_id'],
            ],
            'payment_programs' => [
                'gos_payment_programs_branch_id_idx' => ['branch_id', 'id'],
                'gos_payment_programs_customer_status_idx' => ['customer_id', 'status'],
                'gos_payment_programs_program_no_idx' => ['program_no'],
                'gos_payment_programs_order_no_idx' => ['order_no'],
                'gos_payment_programs_subcategory_idx' => ['sub_category_type', 'sub_category_id'],
            ],
            'vouchers' => [
                'gos_vouchers_branch_id_idx' => ['branch_id', 'id'],
                'gos_vouchers_supplier_date_idx' => ['supplier_id', 'date'],
                'gos_vouchers_voucher_no_idx' => ['voucher_no'],
            ],
            'expenses' => [
                'gos_expenses_branch_id_idx' => ['branch_id', 'id'],
                'gos_expenses_supplier_date_idx' => ['supplier_id', 'date'],
            ],
            'productions' => [
                'gos_productions_branch_id_idx' => ['branch_id', 'id'],
                'gos_productions_article_date_idx' => ['article_id', 'date'],
                'gos_productions_worker_date_idx' => ['worker_id', 'date'],
                'gos_productions_ticket_idx' => ['ticket_no'],
            ],
            'production_materials' => [
                'gos_production_materials_production_idx' => ['production_id'],
                'gos_production_materials_inventory_idx' => ['inventory_item_id'],
                'gos_production_materials_branch_idx' => ['branch_id'],
            ],
            'production_tags' => [
                'gos_production_tags_production_idx' => ['production_id'],
                'gos_production_tags_branch_tag_idx' => ['branch_id', 'tag'],
                'gos_production_tags_worker_idx' => ['worker_id'],
            ],
            'physical_quantities' => [
                'gos_physical_quantities_branch_id_idx' => ['branch_id', 'id'],
                'gos_physical_quantities_article_idx' => ['article_id'],
                'gos_physical_quantities_category_idx' => ['category'],
            ],
            'customers' => [
                'gos_customers_user_idx' => ['user_id'],
                'gos_customers_city_idx' => ['city_id'],
                'gos_customers_category_idx' => ['category'],
                'gos_customers_date_idx' => ['date'],
            ],
            'suppliers' => [
                'gos_suppliers_user_idx' => ['user_id'],
                'gos_suppliers_worker_idx' => ['worker_id'],
                'gos_suppliers_date_idx' => ['date'],
            ],
            'statement_adjustments' => [
                'gos_statement_adjustments_adjustable_idx' => ['adjustable_type', 'adjustable_id'],
                'gos_statement_adjustments_branch_idx' => ['branch_id'],
            ],
            'inventory_items' => [
                'gos_inventory_items_branch_active_idx' => ['branch_id', 'is_active'],
                'gos_inventory_items_name_idx' => ['name'],
            ],
            'inventory_transactions' => [
                'gos_inventory_transactions_item_date_idx' => ['inventory_item_id', 'date'],
                'gos_inventory_transactions_branch_direction_idx' => ['branch_id', 'direction'],
                'gos_inventory_transactions_supplier_date_idx' => ['supplier_id', 'date'],
            ],
            'setups' => [
                'gos_setups_type_idx' => ['type'],
                'gos_setups_title_idx' => ['title'],
            ],
            'bank_accounts' => [
                'gos_bank_accounts_category_idx' => ['category'],
                'gos_bank_accounts_status_idx' => ['status'],
                'gos_bank_accounts_subcategory_idx' => ['sub_category_type', 'sub_category_id'],
            ],
            'users' => [
                'gos_users_status_idx' => ['status'],
                'gos_users_role_idx' => ['role'],
            ],
        ];
    }

    private function createIndexIfMissing(string $table, string $name, array $columns): void
    {
        $columns = array_values(array_filter(
            $columns,
            fn (string $column): bool => Schema::hasColumn($table, $column)
        ));

        if ($columns === [] || $this->indexExists($table, $name)) {
            return;
        }

        $wrappedColumns = implode(', ', array_map(fn (string $column): string => $this->wrap($column), $columns));
        DB::statement('CREATE INDEX ' . $this->wrap($name) . ' ON ' . $this->wrap($table) . ' (' . $wrappedColumns . ')');
    }

    private function indexExists(string $table, string $name): bool
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'sqlite') {
            $indexes = DB::select('PRAGMA index_list(' . $this->wrap($table) . ')');

            return collect($indexes)->contains(fn ($index): bool => ($index->name ?? null) === $name);
        }

        if ($driver === 'mysql') {
            return collect(DB::select('SHOW INDEX FROM ' . $this->wrap($table) . ' WHERE Key_name = ?', [$name]))->isNotEmpty();
        }

        if ($driver === 'pgsql') {
            return (bool) DB::table('pg_indexes')
                ->where('tablename', $table)
                ->where('indexname', $name)
                ->exists();
        }

        return false;
    }

    private function wrap(string $identifier): string
    {
        return DB::connection()->getQueryGrammar()->wrap($identifier);
    }
};
