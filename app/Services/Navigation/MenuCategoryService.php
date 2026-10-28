<?php

namespace App\Services\Navigation;

use App\Services\Settings\AppSettingService;

class MenuCategoryService
{
    public const SETTING_KEY = 'menu_category_assignments';
    public const CATEGORIES_SETTING_KEY = 'menu_categories';
    public const SIDEBAR_MODE_SETTING_KEY = 'sidebar_navigation_mode';

    public function __construct(protected AppSettingService $settings)
    {
    }

    public function categories(): array
    {
        $saved = $this->settings->get(self::CATEGORIES_SETTING_KEY, null, 'installation');

        if (is_array($saved)) {
            return $this->sanitizeCategories($saved);
        }

        return $this->defaultCategories();
    }

    public function sidebarMode(): string
    {
        $mode = $this->settings->get(self::SIDEBAR_MODE_SETTING_KEY, 'modules', 'installation');

        return in_array($mode, ['modules', 'categories'], true) ? $mode : 'modules';
    }

    public function saveSidebarMode(string $mode): void
    {
        $this->settings->set(self::SIDEBAR_MODE_SETTING_KEY, $mode, 'string', 'installation');
    }

    public function defaultCategories(): array
    {
        return [
            ['id' => 'sales', 'name' => 'Sales'],
            ['id' => 'purchase', 'name' => 'Purchase & Suppliers'],
            ['id' => 'inventory', 'name' => 'Inventory & Production'],
            ['id' => 'logistics', 'name' => 'Dispatch & Logistics'],
            ['id' => 'finance', 'name' => 'Accounts & Finance'],
            ['id' => 'hr', 'name' => 'HR & Payroll'],
            ['id' => 'reports', 'name' => 'Reports'],
            ['id' => 'administration', 'name' => 'Administration'],
        ];
    }

    public function categoryIds(): array
    {
        return array_column($this->categories(), 'id');
    }

    public function moduleIds(): array
    {
        return array_keys($this->baseAssignments());
    }

    public function defaultAssignments(): array
    {
        return $this->sanitizeAssignments($this->baseAssignments(), $this->categories());
    }

    public function assignments(): array
    {
        $saved = $this->settings->get(self::SETTING_KEY, [], 'installation');

        $assignments = is_array($saved) ? $saved : $this->baseAssignments();

        return $this->sanitizeAssignments($assignments, $this->categories());
    }

    public function saveConfiguration(array $categories, array $assignments): array
    {
        $sanitizedCategories = $this->sanitizeCategories($categories);
        $sanitizedAssignments = $this->sanitizeAssignments($assignments, $sanitizedCategories);

        $this->settings->set(self::CATEGORIES_SETTING_KEY, $sanitizedCategories, 'json', 'installation');
        $this->settings->set(self::SETTING_KEY, $sanitizedAssignments, 'json', 'installation');

        return [
            'categories' => $sanitizedCategories,
            'assignments' => $sanitizedAssignments,
        ];
    }

    private function sanitizeCategories(array $categories): array
    {
        $sanitized = [];
        $seenIds = [];

        foreach ($categories as $category) {
            $id = trim((string) ($category['id'] ?? ''));
            $name = trim((string) ($category['name'] ?? ''));

            if ($id === '' || $name === '' || isset($seenIds[$id])) {
                continue;
            }

            $seenIds[$id] = true;
            $sanitized[] = ['id' => $id, 'name' => $name];
        }

        return $sanitized;
    }

    private function sanitizeAssignments(array $assignments, array $categories): array
    {
        $categoryIds = array_column($categories, 'id');
        $sanitized = array_fill_keys($this->moduleIdsFromBase(), null);

        foreach ($assignments as $moduleId => $categoryId) {
            if (array_key_exists($moduleId, $sanitized) && ($categoryId === null || in_array($categoryId, $categoryIds, true))) {
                $sanitized[$moduleId] = $categoryId;
            }
        }

        return $sanitized;
    }

    private function moduleIdsFromBase(): array
    {
        return array_keys($this->baseAssignments());
    }

    private function baseAssignments(): array
    {
        return [
            'customers' => 'sales',
            'orders' => 'sales',
            'invoices' => 'sales',
            'sales-returns' => 'sales',
            'customer-payments' => 'sales',
            'suppliers' => 'purchase',
            'vouchers' => 'purchase',
            'articles' => 'inventory',
            'physical-quantities' => 'inventory',
            'inventory' => 'inventory',
            'fabrics' => 'inventory',
            'productions' => 'inventory',
            'shipments' => 'logistics',
            'cargos' => 'logistics',
            'bilties' => 'logistics',
            'payment-programs' => 'finance',
            'expenses' => 'finance',
            'bank-accounts' => 'finance',
            'daily-ledger' => 'finance',
            'balance-entries' => 'finance',
            'cr' => 'finance',
            'dr' => 'finance',
            'utility-bills' => 'finance',
            'utility-accounts' => 'finance',
            'employees' => 'hr',
            'employee-payments' => 'hr',
            'attendances' => 'hr',
            'reports' => 'reports',
            'statement' => 'reports',
            'users' => 'administration',
        ];
    }
}
