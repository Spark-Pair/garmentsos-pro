<?php

namespace App\Services\Navigation;

use App\Services\Settings\AppSettingService;

class MenuCategoryService
{
    public const SETTING_KEY = 'menu_category_assignments';

    public function __construct(protected AppSettingService $settings)
    {
    }

    public function categories(): array
    {
        return [
            ['id' => 'sales', 'name' => 'Sales', 'module_ids' => ['customers', 'orders', 'invoices', 'sales-returns', 'customer-payments']],
            ['id' => 'purchase', 'name' => 'Purchase & Suppliers', 'module_ids' => ['suppliers', 'vouchers']],
            ['id' => 'inventory', 'name' => 'Inventory & Production', 'module_ids' => ['articles', 'physical-quantities', 'inventory', 'fabrics', 'productions']],
            ['id' => 'logistics', 'name' => 'Dispatch & Logistics', 'module_ids' => ['shipments', 'cargos', 'bilties']],
            ['id' => 'finance', 'name' => 'Accounts & Finance', 'module_ids' => ['payment-programs', 'expenses', 'bank-accounts', 'daily-ledger', 'balance-entries', 'cr', 'dr', 'utility-bills', 'utility-accounts']],
            ['id' => 'hr', 'name' => 'HR & Payroll', 'module_ids' => ['employees', 'employee-payments', 'attendances']],
            ['id' => 'reports', 'name' => 'Reports', 'module_ids' => ['reports', 'statement']],
            ['id' => 'administration', 'name' => 'Administration', 'module_ids' => ['users']],
        ];
    }

    public function categoryIds(): array
    {
        return array_column($this->categories(), 'id');
    }

    public function moduleIds(): array
    {
        return array_keys($this->defaultAssignments());
    }

    public function defaultAssignments(): array
    {
        $assignments = [];

        foreach ($this->categories() as $category) {
            foreach ($category['module_ids'] as $moduleId) {
                $assignments[$moduleId] = $category['id'];
            }
        }

        return $assignments;
    }

    public function assignments(): array
    {
        $saved = $this->settings->get(self::SETTING_KEY, [], 'installation');

        return $this->sanitizeAssignments(is_array($saved) ? $saved : []);
    }

    public function saveAssignments(array $assignments): array
    {
        $sanitized = $this->sanitizeAssignments($assignments);

        $this->settings->set(self::SETTING_KEY, $sanitized, 'json', 'installation');

        return $sanitized;
    }

    private function sanitizeAssignments(array $assignments): array
    {
        $sanitized = $this->defaultAssignments();
        $categoryIds = $this->categoryIds();

        foreach ($assignments as $moduleId => $categoryId) {
            if (array_key_exists($moduleId, $sanitized) && in_array($categoryId, $categoryIds, true)) {
                $sanitized[$moduleId] = $categoryId;
            }
        }

        return $sanitized;
    }
}
