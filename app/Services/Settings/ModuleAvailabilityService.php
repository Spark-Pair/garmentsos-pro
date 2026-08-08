<?php

namespace App\Services\Settings;

class ModuleAvailabilityService
{
    private const REQUIRED_MODULES = [
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
    ];

    public function __construct(protected ModuleSettingsService $modules)
    {
    }

    public function all(): array
    {
        return collect($this->modules->registry())
            ->keys()
            ->map(fn (string $key) => $this->effectiveState($key))
            ->values()
            ->all();
    }

    public function effectiveState(string $moduleKey): array
    {
        if (in_array($moduleKey, self::REQUIRED_MODULES, true)) {
            return $this->state(
                $moduleKey,
                true,
                true,
                'required_system_module',
                true,
                null,
                true,
                true,
                'Required system module.',
            );
        }

        $registry = $this->modules->registry();

        if (!array_key_exists($moduleKey, $registry)) {
            return $this->state(
                $moduleKey,
                false,
                false,
                'unknown_module',
                false,
                null,
                null,
                false,
                'Unknown module.',
            );
        }

        $local = $this->modules->effectiveState($moduleKey);
        $defaultEnabled = (bool) ($registry[$moduleKey]['default_enabled'] ?? true);
        $localEnabled = ($local['has_override'] ?? false) ? (bool) $local['enabled'] : null;
        $localVisible = ($local['has_override'] ?? false) ? (bool) $local['visible_in_sidebar'] : null;
        if ($localEnabled === false) {
            return $this->state(
                $moduleKey,
                true,
                false,
                'disabled_locally',
                $defaultEnabled,
                null,
                false,
                false,
                'This module is disabled in developer settings.',
                $local,
            );
        }

        $available = $localEnabled ?? $defaultEnabled;
        $visible = $available && ($localVisible ?? true);
        $reason = $localEnabled === null ? 'default_allowed' : 'available';

        return $this->state(
            $moduleKey,
            true,
            $available,
            $available ? $reason : 'disabled_locally',
            $defaultEnabled,
            null,
            $localEnabled,
            $visible,
            $available ? 'Available.' : 'This module is disabled.',
            $local,
        );
    }

    public function isEffectivelyEnabled(string $moduleKey): bool
    {
        return (bool) $this->effectiveState($moduleKey)['available'];
    }

    public function isEffectivelyVisibleInSidebar(string $moduleKey): bool
    {
        return (bool) $this->effectiveState($moduleKey)['visible_in_sidebar'];
    }

    protected function state(
        string $key,
        bool $known,
        bool $available,
        string $reason,
        bool $defaultEnabled,
        ?bool $licenseAllowed,
        ?bool $localEnabled,
        bool $visible,
        string $message,
        array $local = [],
    ): array {
        return array_merge($local, [
            'key' => $key,
            'known' => $known,
            'available' => $available,
            'reason' => $reason,
            'default_enabled' => $defaultEnabled,
            'license_allowed' => $licenseAllowed,
            'local_enabled' => $localEnabled,
            'effective_enabled' => $available,
            'effective_visible_in_sidebar' => $visible,
            'visible_in_sidebar' => $visible,
            'message' => $message,
        ]);
    }
}
