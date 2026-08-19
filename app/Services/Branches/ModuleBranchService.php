<?php

namespace App\Services\Branches;

use App\Models\Branch;
use App\Models\BranchModuleSetting;
use App\Models\BranchUserAccess;
use App\Models\User;
use App\Models\UserModuleBranchPreference;
use App\Services\Settings\BrandingSettingsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class ModuleBranchService
{
    public const MODULE_REGISTRY = [
        'dashboard' => [
            'label' => 'Dashboard',
            'group' => 'General',
            'route_prefixes' => ['home'],
            'page_reference' => 'home',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Global summary screen. Branch filtering can be added later when dashboard totals are reviewed.',
        ],
        'articles' => [
            'label' => 'Articles',
            'group' => 'Inventory',
            'route_prefixes' => ['articles', 'add-rate', 'update-image'],
            'page_reference' => 'articles',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Safe for branch switching and record filtering.',
        ],
        'article_parts' => [
            'label' => 'Article Parts',
            'group' => 'Inventory',
            'route_prefixes' => ['articles'],
            'page_reference' => 'article parts inside articles',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared article configuration. Keep global until parts ownership is reviewed.',
        ],
        'customers' => [
            'label' => 'Customers',
            'group' => 'Parties',
            'route_prefixes' => ['customers'],
            'page_reference' => 'customers',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Customer master data is global by default. When enabled, customer lists and dependent dropdowns follow the selected branch.',
            'dependencies' => 'Use with Orders, Invoices, Customer Payments, Statements, and Reports for branch-wise customer activity.',
        ],
        'suppliers' => [
            'label' => 'Suppliers',
            'group' => 'Parties',
            'route_prefixes' => ['suppliers', 'update-supplier-category'],
            'page_reference' => 'suppliers',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Supplier master data is global by default. When enabled, supplier lists and dependent dropdowns follow the selected branch.',
            'dependencies' => 'Use with Purchases, Supplier Payments, Stock, Statements, and Reports when supplier flows are branch-scoped.',
        ],
        'employees' => [
            'label' => 'Employees / Workers',
            'group' => 'People',
            'route_prefixes' => ['employees', 'update-employee-status'],
            'page_reference' => 'employees',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared worker records by default. Enable branch filtering only if workers should be branch-specific.',
        ],
        'users' => [
            'label' => 'Users',
            'group' => 'People',
            'route_prefixes' => ['users', 'update-user-status', 'users.reset-password'],
            'page_reference' => 'user',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'User accounts remain global by default. Branch filtering affects user management lists only, not login.',
        ],
        'orders' => [
            'label' => 'Orders',
            'group' => 'Sales',
            'route_prefixes' => ['orders', 'get-order-details'],
            'page_reference' => 'orders',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Enable with invoices and customer payments so sales totals remain consistent.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Physical Quantities, Customer Payments, Statements, Reports, Productions.',
        ],
        'invoices' => [
            'label' => 'Invoices',
            'group' => 'Sales',
            'route_prefixes' => ['invoices', 'print-invoices', 'set-invoice-type'],
            'page_reference' => 'invoices',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Enable with related orders, payments, statements, and reports.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Customer Payments, Statements, Reports.',
        ],
        'vouchers' => [
            'label' => 'Vouchers',
            'group' => 'Finance',
            'route_prefixes' => ['vouchers', 'set-voucher-type', 'get-voucher-details'],
            'page_reference' => 'vouchers',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Safe for branch switching and document branding.',
        ],
        'customer_payments' => [
            'label' => 'Customer Payments',
            'group' => 'Finance',
            'route_prefixes' => ['customer-payments', 'get-payments-by-method'],
            'page_reference' => 'customer-payments',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Customer records remain global; payment rows can be branch-scoped.',
            'dependencies' => 'Recommended group: Orders, Invoices, Customer Payments, Statements, Reports.',
        ],
        'supplier_payments' => [
            'label' => 'Supplier Payments',
            'group' => 'Finance',
            'route_prefixes' => ['supplier-payments'],
            'page_reference' => 'supplier-payments',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Financial module. Enable with purchases and supplier reports/statements.',
            'dependencies' => 'Recommended group: Suppliers, Purchases, Supplier Payments, Stock, Reports.',
        ],
        'purchases' => [
            'label' => 'Purchases',
            'group' => 'Purchases',
            'route_prefixes' => ['expenses', 'fabrics'],
            'page_reference' => 'purchase flows',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Enable with supplier payments and stock reports when purchase flows are branch-wise.',
            'dependencies' => 'Recommended group: Suppliers, Purchases, Supplier Payments, Physical Quantities, Reports.',
        ],
        'productions' => [
            'label' => 'Productions',
            'group' => 'Production',
            'route_prefixes' => ['productions', 'set-production-type'],
            'page_reference' => 'productions',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => true,
            'notes' => 'Production tickets and issue/receive lists are branch-aware.',
        ],
        'physical_quantities' => [
            'label' => 'Physical Quantities / Stock',
            'group' => 'Inventory',
            'route_prefixes' => ['physical-quantities', 'set-physical-quantity-report-type'],
            'page_reference' => 'physical-quantities',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Inventory module. Enable with sales/purchase modules that affect stock.',
            'dependencies' => 'Recommended group: Articles, Orders, Invoices, Productions, Purchases, Reports.',
        ],
        'inventory' => [
            'label' => 'Inventory',
            'group' => 'Inventory',
            'route_prefixes' => ['inventory'],
            'page_reference' => 'inventory',
            'table_names' => ['inventory_items', 'inventory_transactions'],
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => true,
            'notes' => 'General stock/material inventory. Production can consume inventory items while manual production materials remain available.',
            'dependencies' => 'Recommended with Productions, Fabrics, Suppliers, Supplier Payments, and Physical Quantities.',
        ],
        'reports' => [
            'label' => 'Reports',
            'group' => 'Reports',
            'route_prefixes' => ['reports'],
            'page_reference' => 'reports',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Reports can be branch-filtered when the underlying transaction modules are enabled.',
            'dependencies' => 'Recommended with Statements, Pending Payments, Invoices, Orders, Physical Quantities.',
        ],
        'statements' => [
            'label' => 'Statements',
            'group' => 'Reports',
            'route_prefixes' => ['reports.statement', 'set-statement-type'],
            'page_reference' => 'reports/statement',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Statements support one branch, multiple branches, or all branches while customers remain global.',
            'dependencies' => 'Recommended with Invoices and Customer Payments for branch-aware customer balances.',
        ],
        'pending_payments' => [
            'label' => 'Pending Payments',
            'group' => 'Reports',
            'route_prefixes' => ['reports.pending-payments'],
            'page_reference' => 'reports/pending-payments',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => true,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Pending payment reports can filter branch-wise customer payment rows.',
            'dependencies' => 'Recommended with Customer Payments, Statements, and Reports.',
        ],
        'settings' => [
            'label' => 'Settings',
            'group' => 'Administration',
            'route_prefixes' => ['setups', 'update-theme', 'change-data-layout'],
            'page_reference' => 'setups/settings helpers',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Developer/global configuration, not branch-scoped.',
        ],
        'stock' => [
            'label' => 'Stock',
            'group' => 'Inventory',
            'route_prefixes' => ['physical-quantities', 'reports.physical-quantity'],
            'page_reference' => 'computed stock',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Computed stock view. Branch scope is controlled by Physical Quantities and sales/purchase modules.',
        ],
        'setups' => [
            'label' => 'Setup Lists / Categories / Cities',
            'group' => 'Administration',
            'route_prefixes' => ['setups', 'get-category-data'],
            'page_reference' => 'setups',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared setup values used by multiple modules.',
        ],
        'categories' => [
            'label' => 'Categories / Setup Values',
            'group' => 'Administration',
            'route_prefixes' => ['setups', 'get-category-data'],
            'page_reference' => 'setups categories',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Shared setup categories used by articles, suppliers, cities, utility types, and similar dropdowns.',
        ],
        'rates' => [
            'label' => 'Rates',
            'group' => 'Inventory',
            'route_prefixes' => ['rates'],
            'page_reference' => 'rates',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Rate definitions remain global until pricing ownership is reviewed.',
        ],
        'bank_accounts' => [
            'label' => 'Bank Accounts',
            'group' => 'Finance',
            'route_prefixes' => ['bank-accounts', 'update-bank-account-status'],
            'page_reference' => 'bank-accounts',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Bank accounts are global finance setup by default.',
        ],
        'bank_transactions' => [
            'label' => 'Bank Transactions / Daily Ledger',
            'group' => 'Finance',
            'route_prefixes' => ['daily-ledger', 'get-utility-accounts'],
            'page_reference' => 'daily-ledger',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Ledger references remain global until branch ownership is reviewed.',
        ],
        'payment_programs' => [
            'label' => 'Payment Programs',
            'group' => 'Finance',
            'route_prefixes' => ['payment-programs', 'get-program-details'],
            'page_reference' => 'payment-programs',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Program schedules remain global until payment flow ownership is reviewed.',
        ],
        'employee_payments' => [
            'label' => 'Employee Payments / Salaries',
            'group' => 'People',
            'route_prefixes' => ['employee-payments', 'attendances'],
            'page_reference' => 'employee-payments',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Salary/payment flows stay global until payroll branch rules are reviewed.',
        ],
        'cr' => [
            'label' => 'Customer Return / CR',
            'group' => 'Finance',
            'route_prefixes' => ['cr', 'set-cr-type'],
            'page_reference' => 'cr',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'CR flow remains global until return/payment branch ownership is reviewed.',
        ],
        'dr' => [
            'label' => 'Debit Return / DR',
            'group' => 'Finance',
            'route_prefixes' => ['dr'],
            'page_reference' => 'dr',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'DR flow remains global until return/payment branch ownership is reviewed.',
        ],
        'daily_ledger' => [
            'label' => 'Daily Ledger',
            'group' => 'Finance',
            'route_prefixes' => ['daily-ledger', 'set-daily-ledger-type'],
            'page_reference' => 'daily-ledger',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Daily ledger remains global until bank transaction branch ownership is reviewed.',
        ],
        'cargo' => [
            'label' => 'Cargo',
            'group' => 'Dispatch',
            'route_prefixes' => ['cargos'],
            'page_reference' => 'cargos',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Cargo lists can be made branch-wise after cargo flow review.',
        ],
        'shipments' => [
            'label' => 'Shipments',
            'group' => 'Dispatch',
            'route_prefixes' => ['shipments', 'get-shipment-details'],
            'page_reference' => 'shipments',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => true,
            'safe_default_enabled' => false,
            'notes' => 'Shipment documents can be branch-wise after dispatch flow review.',
        ],
        'bilties' => [
            'label' => 'Bilties',
            'group' => 'Dispatch',
            'route_prefixes' => ['bilties'],
            'page_reference' => 'bilties',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Bilty numbers are entered against invoices and remain global until dispatch ownership is reviewed.',
        ],
        'sales_returns' => [
            'label' => 'Sales Returns',
            'group' => 'Sales',
            'route_prefixes' => ['sales-returns'],
            'page_reference' => 'sales-return',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => true,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Sales return rows should follow invoice branch when enabled.',
        ],
        'expenses' => [
            'label' => 'Expenses / Fabrics',
            'group' => 'Purchases',
            'route_prefixes' => ['expenses'],
            'page_reference' => 'expenses',
            'branchable' => true,
            'can_filter_records' => true,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => true,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Expense rows can be branch-wise; manually entered reference numbers remain screen-local.',
        ],
        'fabrics' => [
            'label' => 'Fabrics',
            'group' => 'Purchases',
            'route_prefixes' => ['fabrics'],
            'page_reference' => 'fabrics',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Fabric stock and issue/return flows remain global until fabric calculations are reviewed.',
        ],
        'statement_adjustments' => [
            'label' => 'Statement Adjustments',
            'group' => 'Reports',
            'route_prefixes' => ['statement-adjustments'],
            'page_reference' => 'statement-adjustments',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Manual balance adjustments stay global until statement adjustment branch rules are reviewed.',
        ],
        'attendances' => [
            'label' => 'Attendances',
            'group' => 'People',
            'route_prefixes' => ['attendances'],
            'page_reference' => 'attendances',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Attendance and salary slip screens remain global until payroll branch rules are reviewed.',
        ],
        'utility_bills' => [
            'label' => 'Utility Bills',
            'group' => 'Finance',
            'route_prefixes' => ['utility-bills'],
            'page_reference' => 'utility-bills',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Utility bill payments remain global until account ownership is reviewed.',
        ],
        'utility_accounts' => [
            'label' => 'Utility Accounts',
            'group' => 'Finance',
            'route_prefixes' => ['utility-accounts'],
            'page_reference' => 'utility-accounts',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Utility account setup remains global.',
        ],
        'notifications' => [
            'label' => 'Notifications',
            'group' => 'General',
            'route_prefixes' => ['notifications'],
            'page_reference' => 'notifications',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Notification feed is user/system level.',
        ],
        'permission_report' => [
            'label' => 'Permission Report',
            'group' => 'Developer',
            'route_prefixes' => ['permissions-report'],
            'page_reference' => 'permissions-report',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Permission report is installation-wide.',
        ],
        'developer_settings' => [
            'label' => 'Developer Settings',
            'group' => 'Developer',
            'route_prefixes' => ['developer.settings'],
            'page_reference' => 'developer/settings',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'System configuration, not branch-scoped.',
        ],
        'branches' => [
            'label' => 'Branches',
            'group' => 'Developer',
            'route_prefixes' => ['developer.branches', 'module-branch-preferences'],
            'page_reference' => 'developer/branches',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Branch management controls branch behavior and remains global.',
        ],
        'backups' => [
            'label' => 'Backups / Restore',
            'group' => 'Developer',
            'route_prefixes' => ['developer.backups'],
            'page_reference' => 'developer/license backups restore',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Backup and restore are installation-level tools.',
        ],
        'updater' => [
            'label' => 'Updater',
            'group' => 'Developer',
            'route_prefixes' => ['developer.updater', 'updating'],
            'page_reference' => 'developer/updater',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Application update controls are installation-wide.',
        ],
        'license' => [
            'label' => 'License',
            'group' => 'Developer',
            'route_prefixes' => ['developer.license', 'developer.audit-logs'],
            'page_reference' => 'developer/license',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'License/device state is installation-wide, not branch-scoped.',
        ],
        'setup_wizard' => [
            'label' => 'First Run Setup',
            'group' => 'System',
            'route_prefixes' => ['setup'],
            'page_reference' => 'setup',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Installation setup page. Always global/system-level.',
        ],
        'auth' => [
            'label' => 'Login / Session',
            'group' => 'System',
            'route_prefixes' => ['login', 'logout', 'update-last-activity', 'update-menu-shortcuts', 'update-theme'],
            'page_reference' => 'auth',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Authentication/session pages are global.',
        ],
        'subscription' => [
            'label' => 'Subscription / Expired Page',
            'group' => 'System',
            'route_prefixes' => ['subscription-expired'],
            'page_reference' => 'subscription-expired',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Subscription lockout page is installation-wide.',
        ],
        'branch_assets' => [
            'label' => 'Branch Logos / Assets',
            'group' => 'System',
            'route_prefixes' => ['branch-logos'],
            'page_reference' => 'branch logo delivery',
            'branchable' => false,
            'can_filter_records' => false,
            'can_use_branch_branding' => false,
            'supports_branch_selector' => false,
            'supports_multi_branch_selector' => false,
            'supports_branch_serial_prefix' => false,
            'safe_default_enabled' => false,
            'notes' => 'Asset delivery route for branch logos.',
        ],
    ];

    public const MODULES = [
        'dashboard' => 'Dashboard',
        'articles' => 'Articles',
        'article_parts' => 'Article Parts',
        'customers' => 'Customers',
        'suppliers' => 'Suppliers',
        'employees' => 'Employees / Workers',
        'users' => 'Users',
        'orders' => 'Orders',
        'invoices' => 'Invoices',
        'vouchers' => 'Vouchers',
        'customer_payments' => 'Customer Payments',
        'supplier_payments' => 'Supplier Payments',
        'purchases' => 'Purchases',
        'productions' => 'Productions',
        'physical_quantities' => 'Physical Quantities / Stock',
        'inventory' => 'Inventory',
        'reports' => 'Reports',
        'statements' => 'Statements',
        'pending_payments' => 'Pending Payments',
        'settings' => 'Settings',
        'stock' => 'Stock',
        'setups' => 'Setup Lists / Categories / Cities',
        'categories' => 'Categories / Setup Values',
        'rates' => 'Rates',
        'bank_accounts' => 'Bank Accounts',
        'bank_transactions' => 'Bank Transactions / Daily Ledger',
        'payment_programs' => 'Payment Programs',
        'employee_payments' => 'Employee Payments / Salaries',
        'cr' => 'Customer Return / CR',
        'dr' => 'Debit Return / DR',
        'daily_ledger' => 'Daily Ledger',
        'cargo' => 'Cargo',
        'shipments' => 'Shipments',
        'bilties' => 'Bilties',
        'sales_returns' => 'Sales Returns',
        'expenses' => 'Expenses / Fabrics',
        'fabrics' => 'Fabrics',
        'statement_adjustments' => 'Statement Adjustments',
        'attendances' => 'Attendances',
        'utility_bills' => 'Utility Bills',
        'utility_accounts' => 'Utility Accounts',
        'notifications' => 'Notifications',
        'permission_report' => 'Permission Report',
        'developer_settings' => 'Developer Settings',
        'branches' => 'Branches',
        'backups' => 'Backups / Restore',
        'updater' => 'Updater',
        'license' => 'License',
        'setup_wizard' => 'First Run Setup',
        'auth' => 'Login / Session',
        'subscription' => 'Subscription / Expired Page',
        'branch_assets' => 'Branch Logos / Assets',
    ];

    private ?array $moduleRegistryCache = null;
    private array $moduleConfigCache = [];
    private array $availableBranchesCache = [];
    private array $selectedBranchCache = [];
    private array $selectedBranchIdsCache = [];
    private array $settingCache = [];
    private array $branchSettingCache = [];
    private array $branchSettingsPriorityCache = [];
    private array $isEnabledCache = [];
    private array $isBranchEnabledCache = [];
    private array $branchAllowsSwitchingCache = [];
    private array $shouldFilterRecordsCache = [];
    private array $schemaTableCache = [];
    private array $schemaColumnCache = [];
    private ?bool $moduleSettingsHaveBranchIdCache = null;
    private ?bool $branchTablesReadyCache = null;
    private ?Branch $mainBranchCache = null;
    private bool $branchReadinessWarningLogged = false;

    private const DEVELOPER_MODULE_OVERRIDE_KEYS = [
        'branchable',
        'supports_branch_selector',
        'supports_multi_branch_selector',
        'supports_record_filtering',
        'can_filter_records',
        'has_branch_id_support',
        'supports_branch_branding',
        'can_use_branch_branding',
        'supports_serial_prefix',
        'supports_branch_serial_prefix',
        'supports_doc_identity_prefix',
        'is_system_module',
    ];

    public function moduleRegistry(): array
    {
        return $this->moduleRegistryCache ??= app(BranchModuleRegistryService::class)->registry();
    }

    public function moduleConfig(string $moduleKey): ?array
    {
        $moduleKey = app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);

        if (!array_key_exists($moduleKey, $this->moduleConfigCache)) {
            $this->moduleConfigCache[$moduleKey] = $this->moduleRegistry()[$moduleKey] ?? null;
        }

        return $this->moduleConfigCache[$moduleKey];
    }

    public function runtimeModuleConfig(string $moduleKey, ?int $branchId = null): array
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $config = $this->moduleConfig($moduleKey) ?? [];
        $setting = null;

        if ($branchId) {
            $setting = $this->branchSetting($moduleKey, $branchId);
        }

        $setting ??= $this->setting($moduleKey);

        return $this->applyDeveloperModuleOverrides($config, $setting);
    }

    private function applyDeveloperModuleOverrides(array $config, ?BranchModuleSetting $setting): array
    {
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

        if ($setting) {
            $config['branch_enabled'] = (bool) $setting->branch_enabled;
            $config['branchable'] = (bool) $setting->allow_user_switching;
            $config['supports_branch_selector'] = (bool) $setting->allow_user_switching;
            $config['is_system_module'] = ! (bool) $setting->allow_user_switching;
            $config['status'] = $setting->status ?? ($config['status'] ?? 'active');
            $config['default_branch_id'] = $setting->default_branch_id;
        }

        foreach (self::DEVELOPER_MODULE_OVERRIDE_KEYS as $key) {
            if (array_key_exists($key, $metadata)) {
                $config[$key] = (bool) $metadata[$key];
            }
        }

        if (array_key_exists('record_filtering_enabled', $metadata)) {
            $enabled = (bool) $metadata['record_filtering_enabled'];
            $config['record_filtering_enabled'] = $enabled;
            $config['supports_record_filtering'] = $enabled;
            $config['can_filter_records'] = $enabled;
        }

        if (array_key_exists('doc_identity_prefix', $metadata)) {
            $config['doc_identity_prefix'] = (string) $metadata['doc_identity_prefix'];
        }
        if (array_key_exists('label_override', $metadata) && filled($metadata['label_override'])) {
            $config['label'] = (string) $metadata['label_override'];
        }

        return $config;
    }

    public function isRegisteredModule(string $moduleKey): bool
    {
        return $this->moduleConfig($moduleKey) !== null;
    }

    public function canonicalModuleKey(string $moduleKey): string
    {
        return app(BranchModuleRegistryService::class)->canonicalKey($moduleKey);
    }

    public function moduleLabels(): array
    {
        $labels = app(BranchModuleRegistryService::class)->labels();

        if (!$this->schemaHasTable('branch_module_settings')) {
            return $labels;
        }

        BranchModuleSetting::query()
            ->whereNull('branch_id')
            ->get(['module_key', 'metadata'])
            ->each(function (BranchModuleSetting $setting) use (&$labels) {
                $metadata = is_array($setting->metadata) ? $setting->metadata : [];
                $label = trim((string) ($metadata['label_override'] ?? ''));
                if ($label !== '') {
                    $labels[$this->canonicalModuleKey($setting->module_key)] = $label;
                }
            });

        return $labels;
    }

    private function moduleKeyCandidates(string $moduleKey): array
    {
        $canonical = $this->canonicalModuleKey($moduleKey);
        $candidates = array_merge([$canonical], app(BranchModuleRegistryService::class)->aliasesFor($moduleKey));

        if (str_starts_with($canonical, 'reports_')) {
            $candidates[] = 'reports';
        }

        return array_values(array_unique($candidates));
    }

    private function firstSettingByModuleKeyPriority($settings, array $keys): ?BranchModuleSetting
    {
        $settings = collect($settings);

        foreach ($keys as $key) {
            $setting = $settings->first(fn (BranchModuleSetting $candidate) => $candidate->module_key === $key);
            if ($setting) {
                return $setting;
            }
        }

        return null;
    }

    private function branchSettingsByModuleKeyPriority(string $moduleKey, int $branchId)
    {
        $keys = $this->moduleKeyCandidates($moduleKey);
        $cacheKey = $moduleKey . ':' . $branchId;
        if (array_key_exists($cacheKey, $this->branchSettingsPriorityCache)) {
            return $this->branchSettingsPriorityCache[$cacheKey];
        }

        return $this->branchSettingsPriorityCache[$cacheKey] = BranchModuleSetting::query()
            ->where('branch_id', $branchId)
            ->whereIn('module_key', $keys)
            ->get()
            ->sortBy(fn (BranchModuleSetting $setting) => array_search($setting->module_key, $keys, true))
            ->values();
    }

    private function canFallbackFromSetting(string $moduleKey, BranchModuleSetting $setting): bool
    {
        $metadata = is_array($setting->metadata) ? $setting->metadata : [];

        return !(bool) $setting->branch_enabled
            && (bool) ($metadata['registry_repair'] ?? false);
    }

    private function fallbackCandidateSettingEnabled(string $moduleKey, ?int $branchId = null, bool $requireSwitching = false): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $candidateKeys = array_values(array_diff($this->moduleKeyCandidates($moduleKey), [$moduleKey]));
        if ($candidateKeys === [] || !$this->schemaHasTable('branch_module_settings')) {
            return false;
        }

        $query = BranchModuleSetting::query()->whereIn('module_key', $candidateKeys);
        if ($this->moduleSettingsHaveBranchId()) {
            $branchId === null
                ? $query->whereNull('branch_id')
                : $query->where('branch_id', $branchId);
        }

        return $query->get()
            ->sortBy(fn (BranchModuleSetting $setting) => array_search($setting->module_key, $candidateKeys, true))
            ->contains(fn (BranchModuleSetting $setting) => ($requireSwitching || $setting->branch_enabled)
                && $setting->status === 'active'
                && (!$requireSwitching || $setting->allow_user_switching));
    }

    public function ensureMainBranch(array $details = []): Branch
    {
        $branch = Branch::query()->where('is_main', true)->first();
        if ($branch) {
            $this->ensureRegistryModuleSettings($branch);
            return $branch;
        }

        return DB::transaction(function () use ($details): Branch {
            $name = trim((string) ($details['name'] ?? $details['company_name'] ?? 'Main Branch'));
            $branch = Branch::query()->create([
                'name' => $name !== '' ? $name : 'Main Branch',
                'code' => 'MAIN',
                'prefix' => 'MAIN',
                'status' => 'active',
                'logo_path' => $details['logo_path'] ?? null,
                'display_name' => $details['display_name'] ?? $name,
                'owner_name' => $details['owner_name'] ?? null,
                'header_text' => $details['header_text'] ?? $name,
                'footer_text' => $details['footer_text'] ?? null,
                'terms_text' => $details['terms_text'] ?? null,
                'phone' => $details['phone'] ?? null,
                'email' => $details['email'] ?? null,
                'address' => $details['address'] ?? null,
                'city' => $details['city'] ?? null,
                'province' => $details['province'] ?? null,
                'ntn_cnic' => $details['ntn_cnic'] ?? null,
                'strn_sntn' => $details['strn_sntn'] ?? null,
                'is_main' => true,
                'metadata' => ['created_by' => 'setup'],
            ]);

            $this->ensureRegistryModuleSettings($branch);

            foreach (['developer', 'owner', 'admin', 'manager', 'accountant', 'store_keeper', 'guest', 'supplier'] as $role) {
                BranchUserAccess::query()->firstOrCreate(
                    [
                        'branch_id' => $branch->id,
                        'role' => $role,
                        'module_key' => null,
                    ],
                    [
                        'can_view' => true,
                        'can_create' => true,
                        'can_update' => !in_array($role, ['guest', 'supplier'], true),
                        'can_delete' => $role === 'developer',
                        'can_switch' => in_array($role, ['developer', 'owner', 'admin'], true),
                        'can_manage' => in_array($role, ['developer', 'owner', 'admin'], true),
                    ],
                );
            }

            return $branch;
        });
    }

    public function ensureRegistryModuleSettings(?Branch $mainBranch = null): void
    {
        if (!$this->schemaHasTable('branch_module_settings')) {
            return;
        }

        $mainBranch ??= $this->mainBranch();

        $this->ensureGlobalModuleRows($mainBranch);

        if ($mainBranch) {
            $this->ensureMainBranchHasAllModules($mainBranch);
            $this->grantManagerAccess($mainBranch);
        }
    }

    public function ensureMainBranchHasAllModules(?Branch $mainBranch = null): void
    {
        if (!$this->schemaHasTable('branches') || !$this->schemaHasTable('branch_module_settings') || !$this->moduleSettingsHaveBranchId()) {
            return;
        }

        $mainBranch ??= $this->mainBranch();
        if (!$mainBranch) {
            return;
        }

        foreach ($this->moduleRegistry() as $moduleKey => $config) {
            BranchModuleSetting::query()->firstOrCreate(
                ['branch_id' => $mainBranch->id, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => true,
                    'default_branch_id' => $mainBranch->id,
                    'allow_user_switching' => true,
                    'status' => 'active',
                    'metadata' => [
                        'label' => $config['label'],
                        'group' => $config['group'],
                        'route_prefixes' => $config['route_prefixes'],
                        'page_reference' => $config['page_reference'],
                        'main_branch_default' => true,
                    ],
                ],
            );
        }
    }

    public function ensureGlobalModuleRows(?Branch $mainBranch = null): void
    {
        if (!$this->schemaHasTable('branch_module_settings')) {
            return;
        }

        $mainBranch ??= $this->schemaHasTable('branches')
            ? Branch::query()->where('is_main', true)->first()
            : null;

        foreach ($this->moduleRegistry() as $moduleKey => $config) {
            $safeDefault = (bool) ($config['safe_default_enabled'] ?? false);
            $globalKey = $this->moduleSettingsHaveBranchId()
                ? ['branch_id' => null, 'module_key' => $moduleKey]
                : ['module_key' => $moduleKey];

            BranchModuleSetting::query()->firstOrCreate($globalKey, [
                'branch_enabled' => $safeDefault,
                'default_branch_id' => $mainBranch?->id,
                'allow_user_switching' => $safeDefault,
                'status' => 'active',
                'metadata' => [
                    'label' => $config['label'],
                    'group' => $config['group'],
                    'route_prefixes' => $config['route_prefixes'],
                    'page_reference' => $config['page_reference'],
                    'global_registry_repair' => true,
                ],
            ]);
        }
    }

    public function setting(string $moduleKey): ?BranchModuleSetting
    {
        if (!$this->schemaHasTable('branch_module_settings')) {
            return null;
        }

        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (array_key_exists($moduleKey, $this->settingCache)) {
            return $this->settingCache[$moduleKey];
        }

        $keys = $this->moduleKeyCandidates($moduleKey);
        $query = BranchModuleSetting::query()->whereIn('module_key', $keys);
        if ($this->moduleSettingsHaveBranchId()) {
            $query->whereNull('branch_id');
        }

        $setting = $this->firstSettingByModuleKeyPriority($query->get(), $keys)
            ?? $this->firstSettingByModuleKeyPriority(BranchModuleSetting::query()->whereIn('module_key', $keys)->get(), $keys);
        if (!$setting && $this->isRegisteredModule($moduleKey)) {
            $this->ensureGlobalModuleRows();

            $query = BranchModuleSetting::query()->whereIn('module_key', $keys);
            if ($this->moduleSettingsHaveBranchId()) {
                $query->whereNull('branch_id');
            }

            $setting = $this->firstSettingByModuleKeyPriority($query->get(), $keys)
                ?? $this->firstSettingByModuleKeyPriority(BranchModuleSetting::query()->whereIn('module_key', $keys)->get(), $keys);
        }

        return $this->settingCache[$moduleKey] = $setting;
    }

    public function branchSetting(string $moduleKey, ?int $branchId): ?BranchModuleSetting
    {
        if (!$branchId || !$this->schemaHasTable('branch_module_settings') || !$this->moduleSettingsHaveBranchId()) {
            return null;
        }

        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $cacheKey = $moduleKey . ':' . $branchId;
        if (array_key_exists($cacheKey, $this->branchSettingCache)) {
            return $this->branchSettingCache[$cacheKey];
        }

        return $this->branchSettingCache[$cacheKey] = $this->firstSettingByModuleKeyPriority(BranchModuleSetting::query()
            ->where('branch_id', $branchId)
            ->whereIn('module_key', $this->moduleKeyCandidates($moduleKey))
            ->get(), $this->moduleKeyCandidates($moduleKey));
    }

    public function isEnabled(string $moduleKey): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (array_key_exists($moduleKey, $this->isEnabledCache)) {
            return $this->isEnabledCache[$moduleKey];
        }

        if (!$this->isRegisteredModule($moduleKey) || !$this->branchTablesReadyForSelectors()) {
            return $this->isEnabledCache[$moduleKey] = false;
        }

        $setting = $this->setting($moduleKey);
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
        if ((bool) ($metadata['client_disabled'] ?? false)) {
            return $this->isEnabledCache[$moduleKey] = false;
        }

        if ($setting?->branch_enabled && $setting->status === 'active') {
            return $this->isEnabledCache[$moduleKey] = true;
        }

        if ($this->fallbackCandidateSettingEnabled($moduleKey)) {
            return $this->isEnabledCache[$moduleKey] = true;
        }

        if (!$this->moduleSettingsHaveBranchId()) {
            return $this->isEnabledCache[$moduleKey] = false;
        }

        return $this->isEnabledCache[$moduleKey] = BranchModuleSetting::query()
            ->where('module_key', $moduleKey)
            ->whereNotNull('branch_id')
            ->where('branch_enabled', true)
            ->where('status', 'active')
            ->exists();
    }

    public function isBranchEnabled(string $moduleKey, int $branchId): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $cacheKey = $moduleKey . ':' . $branchId;
        if (array_key_exists($cacheKey, $this->isBranchEnabledCache)) {
            return $this->isBranchEnabledCache[$cacheKey];
        }

        if (!$this->isRegisteredModule($moduleKey) || !$this->branchTablesReadyForSelectors()) {
            return $this->isBranchEnabledCache[$cacheKey] = false;
        }

        $settings = $this->branchSettingsByModuleKeyPriority($moduleKey, $branchId);
        $setting = $settings->first();

        if ($setting) {
            if ($setting->branch_enabled && $setting->status === 'active') {
                return $this->isBranchEnabledCache[$cacheKey] = true;
            }

            if ($this->canFallbackFromSetting($moduleKey, $setting)) {
                $fallback = $settings->skip(1)->first(fn (BranchModuleSetting $candidate) => $candidate->branch_enabled && $candidate->status === 'active');
                if ($fallback) {
                    return $this->isBranchEnabledCache[$cacheKey] = true;
                }
            }

            return $this->isBranchEnabledCache[$cacheKey] = $this->fallbackCandidateSettingEnabled($moduleKey, $branchId)
                || $this->fallbackCandidateSettingEnabled($moduleKey);
        }

        if ($this->fallbackCandidateSettingEnabled($moduleKey, $branchId)) {
            return $this->isBranchEnabledCache[$cacheKey] = true;
        }

        $global = $this->setting($moduleKey);

        return $this->isBranchEnabledCache[$cacheKey] = (bool) ($global?->branch_enabled && $global->status === 'active')
            || $this->fallbackCandidateSettingEnabled($moduleKey);
    }

    public function branchAllowsSwitching(string $moduleKey, int $branchId): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $cacheKey = $moduleKey . ':' . $branchId;
        if (array_key_exists($cacheKey, $this->branchAllowsSwitchingCache)) {
            return $this->branchAllowsSwitchingCache[$cacheKey];
        }

        if (!$this->isRegisteredModule($moduleKey) || !$this->branchTablesReadyForSelectors()) {
            return $this->branchAllowsSwitchingCache[$cacheKey] = false;
        }

        $settings = $this->branchSettingsByModuleKeyPriority($moduleKey, $branchId);
        $setting = $settings->first();

        if ($setting) {
            if ($setting->status === 'active' && $setting->allow_user_switching) {
                return $this->branchAllowsSwitchingCache[$cacheKey] = true;
            }

            if ($this->canFallbackFromSetting($moduleKey, $setting)) {
                $fallback = $settings->skip(1)->first(fn (BranchModuleSetting $candidate) => $candidate->status === 'active' && $candidate->allow_user_switching);
                if ($fallback) {
                    return $this->branchAllowsSwitchingCache[$cacheKey] = true;
                }
            }

            return $this->branchAllowsSwitchingCache[$cacheKey] = $this->fallbackCandidateSettingEnabled($moduleKey, $branchId, requireSwitching: true)
                || $this->fallbackCandidateSettingEnabled($moduleKey, requireSwitching: true);
        }

        return $this->branchAllowsSwitchingCache[$cacheKey] = (bool) $this->setting($moduleKey)?->allow_user_switching
            || $this->fallbackCandidateSettingEnabled($moduleKey, $branchId, requireSwitching: true)
            || $this->fallbackCandidateSettingEnabled($moduleKey, requireSwitching: true);
    }

    public function selectedBranch(string $moduleKey, ?User $user = null): ?Branch
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (!$this->branchTablesReadyForSelectors()) {
            return null;
        }

        $user ??= Auth::user();
        $cacheKey = $moduleKey . ':' . ($user?->id ?? 'guest');
        if (array_key_exists($cacheKey, $this->selectedBranchCache)) {
            return $this->selectedBranchCache[$cacheKey];
        }

        $setting = $this->setting($moduleKey);
        $defaultBranchId = $setting?->default_branch_id;

        if (!$user) {
            return $this->selectedBranchCache[$cacheKey] = ($defaultBranchId
                ? Branch::query()->find($defaultBranchId)
                : Branch::query()->where('is_main', true)->first());
        }

        $preferred = UserModuleBranchPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('module_key', $this->moduleKeyCandidates($moduleKey))
            ->first();

        if ($preferred && $this->branchAllowsSwitching($moduleKey, $preferred->branch_id) && $this->canView($preferred->branch_id, $moduleKey, $user)) {
            return $this->selectedBranchCache[$cacheKey] = Branch::query()->find($preferred->branch_id);
        }

        $branch = $defaultBranchId
            ? Branch::query()->find($defaultBranchId)
            : Branch::query()->where('is_main', true)->first();

        if ($branch && $this->branchAllowsSwitching($moduleKey, $branch->id) && $this->canView($branch->id, $moduleKey, $user)) {
            return $this->selectedBranchCache[$cacheKey] = $branch;
        }

        return $this->selectedBranchCache[$cacheKey] = Branch::query()
            ->where('status', 'active')
            ->orderBy('id')
            ->get()
            ->first(fn (Branch $candidate) => $this->branchAllowsSwitching($moduleKey, $candidate->id) && $this->canView($candidate->id, $moduleKey, $user));
    }

    public function switchableBranches(string $moduleKey, ?User $user = null)
    {
        return $this->availableBranchesForModule($moduleKey, $user);
    }

    public function availableBranchesForModule(string $moduleKey, ?User $user = null)
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $user ??= Auth::user();
        $cacheKey = $moduleKey . ':' . ($user?->id ?? 'guest');
        if (array_key_exists($cacheKey, $this->availableBranchesCache)) {
            return $this->availableBranchesCache[$cacheKey];
        }

        if (!$this->branchTablesReadyForSelectors()) {
            return $this->availableBranchesCache[$cacheKey] = collect();
        }

        if (!$user || !$this->isRegisteredModule($moduleKey)) {
            return $this->availableBranchesCache[$cacheKey] = collect();
        }

        if ($this->canManageBranches($user)) {
            return $this->availableBranchesCache[$cacheKey] = Branch::query()
                ->where('status', 'active')
                ->orderByDesc('is_main')
                ->orderBy('name')
                ->get()
                ->filter(fn (Branch $branch) => $this->branchAllowsSwitching($moduleKey, $branch->id))
                ->values();
        }

        $branchIds = BranchUserAccess::query()
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere('role', $user->role);
            })
            ->where(function (Builder $query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhereIn('module_key', $this->moduleKeyCandidates($moduleKey));
            })
            ->where('can_view', true)
            ->pluck('branch_id')
            ->unique();

        return $this->availableBranchesCache[$cacheKey] = Branch::query()
            ->whereIn('id', $branchIds)
            ->where('status', 'active')
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get()
            ->filter(fn (Branch $branch) => $this->branchAllowsSwitching($moduleKey, $branch->id))
            ->values();
    }

    public function selectedBranchForModule(string $moduleKey, ?User $user = null): ?Branch
    {
        return $this->selectedBranch($moduleKey, $user);
    }

    public function selectedBranchIdForModule(string $moduleKey, ?User $user = null): ?int
    {
        return $this->selectedBranchForModule($moduleKey, $user)?->id;
    }

    public function isModuleBranchEnabled(string $moduleKey, ?int $branchId = null): bool
    {
        return $branchId ? $this->isBranchEnabled($moduleKey, $branchId) : $this->isEnabled($moduleKey);
    }

    public function isClientModuleEnabled(string $moduleKey): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);

        if (in_array($moduleKey, [
            'auth',
            'auth_login',
            'setup',
            'first_run_setup',
            'subscription_expired',
            'branch_logo_delivery',
            'home',
            'dashboard',
            'developer_settings',
            'developer_branches',
            'developer_backups',
            'developer_updater',
            'developer_license',
            'developer_audit_logs',
        ], true)) {
            return true;
        }

        if ($this->schemaHasTable('branch_module_settings') && $this->isRegisteredModule($moduleKey)) {
            $setting = $this->setting($moduleKey);
            $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

            if ((bool) ($metadata['client_disabled'] ?? false)) {
                return false;
            }

            if ($setting && $setting->status === 'inactive') {
                return false;
            }
        }

        if (array_key_exists($moduleKey, app(\App\Services\Settings\ModuleSettingsService::class)->registry())) {
            return app(\App\Services\Settings\ModuleAvailabilityService::class)->isEffectivelyEnabled($moduleKey);
        }

        return $this->isRegisteredModule($moduleKey);
    }

    public function isClientModuleVisible(string $moduleKey): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);

        if (!$this->isClientModuleEnabled($moduleKey)) {
            return false;
        }

        if (array_key_exists($moduleKey, app(\App\Services\Settings\ModuleSettingsService::class)->registry())) {
            return app(\App\Services\Settings\ModuleAvailabilityService::class)->isEffectivelyVisibleInSidebar($moduleKey);
        }

        return true;
    }

    public function enabledWorkflowTypes(array $typeModuleMap): array
    {
        return collect($typeModuleMap)
            ->filter(fn (string $moduleKey) => $this->isClientModuleEnabled($moduleKey))
            ->keys()
            ->values()
            ->all();
    }

    public function shouldFilterRecords(string $moduleKey, ?User $user = null): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $user ??= Auth::user();
        $cacheKey = $moduleKey . ':' . ($user?->id ?? 'guest');
        if (array_key_exists($cacheKey, $this->shouldFilterRecordsCache)) {
            return $this->shouldFilterRecordsCache[$cacheKey];
        }

        if (!$this->branchTablesReadyForSelectors()) {
            return $this->shouldFilterRecordsCache[$cacheKey] = false;
        }

        $setting = null;
        $selectedBranchId = $this->selectedBranchIdForModule($moduleKey, $user);
        if ($selectedBranchId) {
            $setting = $this->branchSetting($moduleKey, $selectedBranchId);
        }
        $setting ??= $this->setting($moduleKey);
        $config = $this->runtimeModuleConfig($moduleKey, $selectedBranchId);
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
        $recordFilteringEnabled = array_key_exists('record_filtering_enabled', $metadata)
            ? (bool) $metadata['record_filtering_enabled']
            : (bool) ($config['can_filter_records'] ?? false);

        return $this->shouldFilterRecordsCache[$cacheKey] = (bool) (
            $user
            && $this->isEnabled($moduleKey)
            && $selectedBranchId
            && $recordFilteringEnabled
            && ($config['has_branch_id_support'] ?? false)
        );
    }

    public function shouldFilterRelatedRecords(?string $currentModuleKey, string $relatedModuleKey, ?User $user = null): bool
    {
        $currentModuleKey = $currentModuleKey ? $this->canonicalModuleKey($currentModuleKey) : $this->currentModuleKey();

        return (bool) (
            $currentModuleKey
            && $this->shouldFilterRecords($currentModuleKey, $user)
            && $this->shouldFilterRecords($relatedModuleKey, $user)
        );
    }

    public function isModuleBranchSwitchable(string $moduleKey, ?User $user = null): bool
    {
        return $this->canShowSelector($moduleKey, $user);
    }

    public function canShowSelector(string $moduleKey, ?User $user = null): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $user ??= Auth::user();
        if (!$this->branchTablesReadyForSelectors()) {
            return false;
        }

        if (!$user || !$this->isRegisteredModule($moduleKey)) {
            return false;
        }

        if ($this->isWriteRoute() && !$this->shouldFilterRecords($moduleKey, $user)) {
            return false;
        }

        return $this->availableBranchesForModule($moduleKey, $user)->count() > 1;
    }

    private function isWriteRoute(): bool
    {
        $route = request()->route();
        $routeName = (string) ($route?->getName() ?? '');
        $action = strtolower((string) ($route?->getActionMethod() ?? ''));
        $method = strtoupper((string) request()->method());

        if ($method !== 'GET') {
            return true;
        }

        foreach (['create', 'store', 'edit', 'update', 'destroy'] as $writeAction) {
            if ($action === $writeAction || Str::endsWith($routeName, '.' . $writeAction)) {
                return true;
            }
        }

        return request()->is('*/create') || request()->is('*/*/edit');
    }

    public function supportsMultiBranchSelector(string $moduleKey): bool
    {
        return $this->branchTablesReadyForSelectors()
            && (bool) ($this->runtimeModuleConfig($moduleKey)['supports_multi_branch_selector'] ?? false);
    }

    public function shouldUseMultiBranchSelector(string $moduleKey): bool
    {
        if (!$this->supportsMultiBranchSelector($moduleKey)) {
            return false;
        }

        $route = request()->route();
        $routeName = (string) ($route?->getName() ?? '');
        $action = strtolower((string) ($route?->getActionMethod() ?? ''));
        $method = strtoupper((string) request()->method());

        if ($method !== 'GET') {
            return false;
        }

        foreach (['create', 'store', 'edit', 'update', 'destroy'] as $writeAction) {
            if ($action === $writeAction || Str::endsWith($routeName, '.' . $writeAction)) {
                return false;
            }
        }

        if (request()->is('*/create') || request()->is('*/*/edit')) {
            return false;
        }

        return true;
    }

    public function selectedBranchIdsForModule(string $moduleKey, ?User $user = null): array
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $user ??= Auth::user();
        $cacheKey = $moduleKey . ':' . ($user?->id ?? 'guest');
        if (array_key_exists($cacheKey, $this->selectedBranchIdsCache)) {
            return $this->selectedBranchIdsCache[$cacheKey];
        }

        $available = $this->availableBranchesForModule($moduleKey, $user);
        if (!$user || $available->isEmpty()) {
            return $this->selectedBranchIdsCache[$cacheKey] = [];
        }

        if (!$this->supportsMultiBranchSelector($moduleKey)) {
            return $this->selectedBranchIdsCache[$cacheKey] = array_values(array_filter([(int) ($this->selectedBranchIdForModule($moduleKey, $user) ?? 0)]));
        }

        $availableIds = $available->pluck('id')->map(fn ($id) => (int) $id)->values();
        $preferred = UserModuleBranchPreference::query()
            ->where('user_id', $user->id)
            ->whereIn('module_key', $this->moduleKeyCandidates($moduleKey))
            ->first();

        $storedIds = collect($preferred?->branch_ids ?? [])
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($availableIds)
            ->values();

        return $this->selectedBranchIdsCache[$cacheKey] = $storedIds->isNotEmpty()
            ? $storedIds->all()
            : $availableIds->all();
    }

    public function selectedBranchNamesForModule(string $moduleKey, ?User $user = null): array
    {
        $selectedIds = $this->selectedBranchIdsForModule($moduleKey, $user);

        return $this->availableBranchesForModule($moduleKey, $user)
            ->whereIn('id', $selectedIds)
            ->pluck('name')
            ->values()
            ->all();
    }

    public function isAllBranchesSelected(string $moduleKey, ?User $user = null): bool
    {
        $available = $this->availableBranchesForModule($moduleKey, $user);
        if ($available->isEmpty()) {
            return false;
        }

        return count($this->selectedBranchIdsForModule($moduleKey, $user)) === $available->count();
    }

    public function reportBranchContext(string $moduleKey, ?User $user = null): array
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $user ??= Auth::user();
        $available = $this->availableBranchesForModule($moduleKey, $user);
        $selectedIds = collect($this->selectedBranchIdsForModule($moduleKey, $user))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->values();

        $selectedBranches = $available->whereIn('id', $selectedIds->all())->values();
        $allSelected = $available->isNotEmpty() && $selectedIds->count() === $available->count();
        $mainBranch = $this->mainBranch();
        $mode = $allSelected ? 'all' : ($selectedIds->count() > 1 ? 'multiple' : 'single');
        $useMainBranding = $mode !== 'single';
        $brandingBranch = $useMainBranding ? $mainBranch : $selectedBranches->first();

        return [
            'module_key' => $moduleKey,
            'mode' => $mode,
            'branch_ids' => $selectedIds->all(),
            'branch_names' => $allSelected
                ? ['All Branches']
                : ($selectedBranches->pluck('name')->values()->all() ?: ['Main Branch']),
            'branches' => $available,
            'selected_branches' => $selectedBranches,
            'branding_branch' => $brandingBranch,
            'use_main_branding' => $useMainBranding,
            'include_null_main_records' => $selectedBranches->contains(fn (Branch $branch) => (bool) $branch->is_main),
        ];
    }

    public function currentModuleKey(): ?string
    {
        try {
            return app(BranchModuleRegistryService::class)->moduleKeyForRoute(request()->route());
        } catch (\Throwable) {
            return null;
        }
    }

    public function selectedBranchSummaryForModule(string $moduleKey, ?User $user = null): string
    {
        $available = $this->availableBranchesForModule($moduleKey, $user);
        $selectedIds = $this->selectedBranchIdsForModule($moduleKey, $user);
        if ($available->isEmpty() || $selectedIds === []) {
            return $this->selectedBranchForModule($moduleKey, $user)?->name ?? 'Select Branch';
        }

        if (count($selectedIds) === $available->count()) {
            return 'All Branches';
        }

        return $available
            ->whereIn('id', $selectedIds)
            ->pluck('name')
            ->implode(' + ');
    }

    public function setPreference(string $moduleKey, int $branchId, User $user): void
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (!$this->isRegisteredModule($moduleKey) || !($this->runtimeModuleConfig($moduleKey, $branchId)['supports_branch_selector'] ?? false)) {
            abort(422, 'Unknown or non-branchable module.');
        }

        if ($this->availableBranchesForModule($moduleKey, $user)->count() <= 1) {
            abort(422, 'This module does not support branch switching.');
        }

        if (!$this->canSwitch($branchId, $moduleKey, $user)) {
            abort(403, 'You cannot switch to this branch for this module.');
        }

        UserModuleBranchPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module_key' => $moduleKey],
            [
                'branch_id' => $branchId,
                'selection_mode' => 'single',
                'branch_ids' => [$branchId],
            ],
        );

        unset($this->selectedBranchCache[$moduleKey . ':' . $user->id], $this->selectedBranchIdsCache[$moduleKey . ':' . $user->id]);
    }

    public function setMultiPreference(string $moduleKey, array $branchIds, User $user): void
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (!$this->supportsMultiBranchSelector($moduleKey)) {
            abort(422, 'This module does not support multi-branch selection.');
        }

        if ($this->availableBranchesForModule($moduleKey, $user)->isEmpty()) {
            abort(422, 'This module does not support branch switching.');
        }

        $availableIds = $this->availableBranchesForModule($moduleKey, $user)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values();

        $selectedIds = collect($branchIds)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->intersect($availableIds)
            ->unique()
            ->values();

        if ($selectedIds->isEmpty()) {
            $selectedIds = $availableIds;
        }

        if ($selectedIds->isEmpty()) {
            abort(422, 'No accessible branches are available for this module.');
        }

        UserModuleBranchPreference::query()->updateOrCreate(
            ['user_id' => $user->id, 'module_key' => $moduleKey],
            [
                'branch_id' => $selectedIds->first(),
                'selection_mode' => 'multiple',
                'branch_ids' => $selectedIds->all(),
            ],
        );

        unset($this->selectedBranchCache[$moduleKey . ':' . $user->id], $this->selectedBranchIdsCache[$moduleKey . ':' . $user->id]);
    }

    public function applyScope(Builder $query, string $moduleKey, string $branchColumn = 'branch_id'): Builder
    {
        if (!$this->shouldFilterRecords($moduleKey)) {
            return $query;
        }

        $model = $query->getModel();
        if (!$this->schemaHasColumn($model->getTable(), $branchColumn)) {
            return $query;
        }

        $qualifiedColumn = $model->getTable() . '.' . $branchColumn;
        $selectedIds = $this->shouldUseMultiBranchSelector($moduleKey)
            ? collect($this->selectedBranchIdsForModule($moduleKey))
                ->filter(fn ($id) => is_numeric($id))
                ->map(fn ($id) => (int) $id)
                ->values()
            : collect();

        if ($selectedIds->isNotEmpty()) {
            $includeNullBranchRecords = Branch::query()
                ->whereIn('id', $selectedIds->all())
                ->where('is_main', true)
                ->exists();

            return $query->where(function (Builder $scoped) use ($qualifiedColumn, $selectedIds, $includeNullBranchRecords) {
                $scoped->whereIn($qualifiedColumn, $selectedIds->all());

                if ($includeNullBranchRecords) {
                    $scoped->orWhereNull($qualifiedColumn);
                }
            });
        }

        $branch = $this->selectedBranch($moduleKey);

        if (!$branch) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $scoped) use ($qualifiedColumn, $branch) {
            $scoped->where($qualifiedColumn, $branch->id);

            if ($branch->is_main) {
                $scoped->orWhereNull($qualifiedColumn);
            }
        });
    }

    public function applyRelatedScope(Builder $query, string $relatedModuleKey, string|int|null $workingModuleKeyOrBranchId = null, string $branchColumn = 'branch_id'): Builder
    {
        $workingModuleKey = is_numeric($workingModuleKeyOrBranchId)
            ? null
            : ($workingModuleKeyOrBranchId ? (string) $workingModuleKeyOrBranchId : $this->currentModuleKey());

        if (!is_numeric($workingModuleKeyOrBranchId) && !$this->shouldFilterRelatedRecords($workingModuleKey, $relatedModuleKey)) {
            return $query;
        }

        if (is_numeric($workingModuleKeyOrBranchId) && !$this->shouldFilterRecords($relatedModuleKey)) {
            return $query;
        }

        $model = $query->getModel();
        if (!$this->schemaHasColumn($model->getTable(), $branchColumn)) {
            return $query;
        }

        $branchId = is_numeric($workingModuleKeyOrBranchId)
            ? (int) $workingModuleKeyOrBranchId
            : ($workingModuleKey
                ? $this->selectedBranchIdForModule((string) $workingModuleKey)
                : null);

        if (!$branchId || !$this->isBranchEnabled($relatedModuleKey, $branchId)) {
            return $query;
        }

        $branch = $this->schemaHasTable('branches') ? Branch::query()->find($branchId) : null;
        $qualifiedColumn = $model->getTable() . '.' . $branchColumn;

        return $query->where(function (Builder $scoped) use ($qualifiedColumn, $branchId, $branch) {
            $scoped->where($qualifiedColumn, $branchId);

            if ($branch?->is_main) {
                $scoped->orWhereNull($qualifiedColumn);
            }
        });
    }

    public function relatedScopeForWorkingModule(Builder $query, string $workingModuleKey, string $relatedModuleKey, string $branchColumn = 'branch_id'): Builder
    {
        return $this->applyRelatedScope($query, $relatedModuleKey, $workingModuleKey, $branchColumn);
    }

    public function branchIdForCreate(string $moduleKey): ?int
    {
        return $this->shouldFilterRecords($moduleKey) ? $this->selectedBranch($moduleKey)?->id : null;
    }

    public function getDefaultOrderDiscountForBranch(?int $branchId = null): int
    {
        $branchId ??= $this->selectedBranchIdForModule('orders') ?? $this->mainBranch()?->id;
        $setting = $branchId ? $this->branchSetting('orders', $branchId) : null;
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];

        if (!array_key_exists('default_order_discount_percent', $metadata)) {
            return 0;
        }

        $discount = (int) $metadata['default_order_discount_percent'];

        return max(0, min(100, $discount));
    }

    public function documentModuleOptions(string $moduleKey, ?object $record = null): array
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $branch = $record ? $this->branchForRecord($record, $moduleKey) : $this->selectedBranch($moduleKey);
        $branch ??= $this->mainBranch();

        return $this->documentModuleOptionsForBranch($moduleKey, $branch?->id);
    }

    public function assignBranchOnCreate(object|array $modelOrData, string $moduleKey, string $branchColumn = 'branch_id'): object|array
    {
        $branchId = $this->branchIdForCreate($moduleKey);
        if (!$branchId) {
            return $modelOrData;
        }

        if (is_array($modelOrData)) {
            $modelOrData[$branchColumn] = $branchId;

            return $modelOrData;
        }

        $modelOrData->{$branchColumn} = $branchId;

        return $modelOrData;
    }

    public function assertRecordInAllowedBranch(object $record, string $moduleKey, string $branchColumn = 'branch_id'): void
    {
        if (!$this->shouldFilterRecords($moduleKey)) {
            return;
        }

        $table = method_exists($record, 'getTable') ? $record->getTable() : null;
        if (!$table || !$this->schemaHasColumn($table, $branchColumn)) {
            return;
        }

        $recordBranchId = data_get($record, $branchColumn);
        $selectedIds = collect($this->selectedBranchIdsForModule($moduleKey))
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($selectedIds->isEmpty()) {
            abort(403, 'No branch is selected for this module.');
        }

        if ($recordBranchId && $selectedIds->contains((int) $recordBranchId)) {
            return;
        }

        if (!$recordBranchId) {
            $mainBranchId = $this->mainBranch()?->id;
            if ($mainBranchId && $selectedIds->contains((int) $mainBranchId)) {
                return;
            }
        }

        abort(403, 'This record does not belong to the selected branch.');
    }

    public function canSwitch(int $branchId, string $moduleKey, User $user): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (!$this->branchAllowsSwitching($moduleKey, $branchId)) {
            return false;
        }

        return $this->canView($branchId, $moduleKey, $user);
    }

    public function canView(int $branchId, string $moduleKey, User $user): bool
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        if (!$this->branchTablesReadyForSelectors()) {
            return false;
        }

        if (!$this->isBranchEnabled($moduleKey, $branchId)) {
            return false;
        }

        return $this->canManageBranches($user) || BranchUserAccess::query()
            ->where('branch_id', $branchId)
            ->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)->orWhere('role', $user->role);
            })
            ->where(function (Builder $query) use ($moduleKey) {
                $query->whereNull('module_key')->orWhereIn('module_key', $this->moduleKeyCandidates($moduleKey));
            })
            ->where('can_view', true)
            ->exists();
    }

    public function canManageBranches(?User $user = null): bool
    {
        $user ??= Auth::user();

        return $user && $user->role === 'developer';
    }

    public function createBranch(array $data): Branch
    {
        return DB::transaction(function () use ($data): Branch {
            $code = strtoupper(preg_replace('/[^A-Z0-9]+/', '-', Str::ascii($data['code'] ?? $data['name'])));
            $code = trim($code, '-') ?: 'BRANCH';
            $baseCode = $code;
            $suffix = 2;
            while (Branch::query()->where('code', $code)->exists()) {
                $code = $baseCode . '-' . $suffix;
                $suffix++;
            }

            $branch = Branch::query()->create([
                'name' => $data['name'],
                'code' => $code,
                'prefix' => $data['prefix'] ?? $code,
                'status' => $data['status'] ?? 'active',
                'phone' => $data['phone'] ?? null,
                'display_name' => $data['display_name'] ?? $data['name'],
                'owner_name' => $data['owner_name'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'province' => $data['province'] ?? null,
                'ntn_cnic' => $data['ntn_cnic'] ?? null,
                'strn_sntn' => $data['strn_sntn'] ?? null,
                'header_text' => $data['header_text'] ?? null,
                'footer_text' => $data['footer_text'] ?? null,
                'terms_text' => $data['terms_text'] ?? null,
                'logo_path' => $data['logo_path'] ?? null,
                'is_main' => false,
                'metadata' => ['created_from' => 'developer_ui'],
            ]);

            $this->grantManagerAccess($branch);
            $this->ensureBranchModuleRows($branch);

            return $branch;
        });
    }

    public function ensureBranchModuleRows(Branch $branch): void
    {
        if (!$this->moduleSettingsHaveBranchId()) {
            return;
        }

        if ($branch->is_main) {
            $this->ensureRegistryModuleSettings($branch);
            return;
        }

        foreach ($this->moduleRegistry() as $moduleKey => $config) {
            $safeDefault = (bool) ($config['safe_default_enabled'] ?? false);
            BranchModuleSetting::query()->firstOrCreate(
                ['branch_id' => $branch->id, 'module_key' => $moduleKey],
                [
                    'branch_enabled' => $safeDefault,
                    'default_branch_id' => $this->mainBranch()?->id,
                    'allow_user_switching' => $safeDefault,
                    'status' => 'active',
                    'metadata' => [
                        'label' => $config['label'],
                        'group' => $config['group'],
                        'route_prefixes' => $config['route_prefixes'],
                        'page_reference' => $config['page_reference'],
                        'created_for_branch' => true,
                    ],
                ],
            );
        }
    }

    public function grantManagerAccess(Branch $branch): void
    {
        if (!$this->schemaHasTable('branch_user_access')) {
            return;
        }

        foreach (['developer', 'owner', 'admin'] as $role) {
            BranchUserAccess::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'role' => $role,
                    'module_key' => null,
                ],
                [
                    'user_id' => null,
                    'can_view' => true,
                    'can_create' => true,
                    'can_update' => true,
                    'can_delete' => $role === 'developer',
                    'can_switch' => true,
                    'can_manage' => true,
                ],
            );
        }
    }

    public function backfillManagerAccess(): void
    {
        if (!$this->schemaHasTable('branches') || !$this->schemaHasTable('branch_user_access')) {
            return;
        }

        $this->ensureRegistryModuleSettings();

        Branch::query()
            ->where(function (Builder $query) {
                $query->where('status', 'active')->orWhere('is_main', true);
            })
            ->get()
            ->each(function (Branch $branch) {
                $this->grantManagerAccess($branch);
                $branch->is_main
                    ? $this->ensureMainBranchHasAllModules($branch)
                    : $this->ensureBranchModuleRows($branch);
            });
    }

    protected function moduleSettingsHaveBranchId(): bool
    {
        return $this->moduleSettingsHaveBranchIdCache ??= ($this->schemaHasTable('branch_module_settings')
            && $this->schemaHasColumn('branch_module_settings', 'branch_id'));
    }

    public function mainBranch(): ?Branch
    {
        if (!$this->schemaHasTable('branches')) {
            return null;
        }

        return $this->mainBranchCache ??= Branch::query()->where('is_main', true)->first()
            ?? Branch::query()->orderBy('id')->first();
    }

    public function syncMainBranchDetails(array $details): void
    {
        $branch = $this->ensureMainBranch($details);
        $updates = [];

        foreach ([
            'display_name',
            'owner_name',
            'logo_path',
            'phone',
            'email',
            'address',
            'city',
            'province',
            'ntn_cnic',
            'strn_sntn',
            'header_text',
            'footer_text',
            'terms_text',
        ] as $field) {
            $value = $details[$field] ?? ($field === 'display_name' ? ($details['company_name'] ?? $details['name'] ?? null) : null);
            if (blank($branch->{$field}) && filled($value)) {
                $updates[$field] = $value;
            }
        }

        if ($updates !== []) {
            $branch->update($updates);
        }
    }

    public function branchForRecord(object $record, string $moduleKey): ?Branch
    {
        if (isset($record->branch) && $record->branch instanceof Branch) {
            return $record->branch;
        }

        $branchId = data_get($record, 'branch_id');
        if ($branchId && $this->schemaHasTable('branches')) {
            return Branch::query()->find($branchId);
        }

        return $this->selectedBranch($moduleKey) ?: $this->mainBranch();
    }

    public function documentBranding(string $moduleKey, ?object $record = null): array
    {
        $branch = $record ? $this->branchForRecord($record, $moduleKey) : $this->selectedBranch($moduleKey);
        $branch ??= $this->mainBranch();
        $app = app(BrandingSettingsService::class)->clientCompany();
        $branchLogoUrl = null;
        $documentOptions = $this->documentModuleOptionsForBranch($moduleKey, $branch?->id);

        if ($branch?->logo_path) {
            $publicLogo = public_path('storage/' . ltrim($branch->logo_path, '/'));
            $branchLogoUrl = is_file($publicLogo)
                ? asset('storage/' . ltrim($branch->logo_path, '/'))
                : route('branch-logos.show', $branch);
        }

        return [
            'source' => $branch ? 'branch' : 'app_settings',
            'branch_id' => $branch?->id,
            'name' => $branch?->displayName() ?: ($app->name ?? config('app.name')),
            'display_name' => $branch?->displayName() ?: ($app->name ?? config('app.name')),
            'owner_name' => $branch?->owner_name,
            'phone' => $branch?->phone ?: ($app->phone_number ?? ''),
            'phone_number' => $branch?->phone ?: ($app->phone_number ?? ''),
            'email' => $branch?->email,
            'address' => $branch?->address,
            'city' => $branch?->city,
            'province' => $branch?->province,
            'ntn_cnic' => $branch?->ntn_cnic,
            'strn_sntn' => $branch?->strn_sntn,
            'header_text' => $branch?->header_text,
            'footer_text' => $branch?->footer_text,
            'terms_text' => $branch?->terms_text,
            'logo' => $branch?->logo_path ?: ($app->logo ?? null),
            'logo_text' => $branch?->header_text ?: ($app->logo_text ?? null),
            'logo_url' => $branchLogoUrl ?: (!empty($app->logo) ? asset('images/' . $app->logo) : null),
            'discount_disabled' => $documentOptions['discount_disabled'],
            'document_note' => $documentOptions['document_note'],
        ];
    }

    protected function documentModuleOptionsForBranch(string $moduleKey, ?int $branchId): array
    {
        $moduleKey = $this->canonicalModuleKey($moduleKey);
        $setting = $branchId ? $this->branchSetting($moduleKey, $branchId) : null;
        $metadata = is_array($setting?->metadata) ? $setting->metadata : [];
        $supportsDocumentOptions = in_array($moduleKey, ['orders', 'invoices'], true);

        return [
            'discount_disabled' => $supportsDocumentOptions && (bool) ($metadata['discount_disabled'] ?? false),
            'document_note' => $supportsDocumentOptions ? trim((string) ($metadata['document_note'] ?? '')) : '',
        ];
    }

    protected function branchTablesReadyForSelectors(): bool
    {
        if ($this->branchTablesReadyCache !== null) {
            return $this->branchTablesReadyCache;
        }

        $missing = array_values(array_filter([
            'branches',
            'branch_module_settings',
            'branch_user_access',
            'user_module_branch_preferences',
        ], fn (string $table): bool => !$this->schemaHasTable($table)));

        if ($missing === []) {
            return $this->branchTablesReadyCache = true;
        }

        if (!$this->branchReadinessWarningLogged) {
            $this->branchReadinessWarningLogged = true;
            Log::warning('Branch selector hidden because branch tables are not ready.', [
                'missing_tables' => $missing,
            ]);
        }

        return $this->branchTablesReadyCache = false;
    }

    private function schemaHasTable(string $table): bool
    {
        return $this->schemaTableCache[$table] ??= Schema::hasTable($table);
    }

    private function schemaHasColumn(string $table, string $column): bool
    {
        $key = $table . '.' . $column;

        return $this->schemaColumnCache[$key] ??= ($this->schemaHasTable($table) && Schema::hasColumn($table, $column));
    }
}
