<?php

namespace App\Services\Settings;

use App\Models\BrandingSetting;
use App\Services\AuditLogService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class BrandingSettingsService
{
    public function __construct(
        protected SettingsCacheService $cache,
        protected AuditLogService $auditLogs,
        protected SettingsValueGuard $valueGuard,
    ) {
    }

    public function defaults(): array
    {
        return config('branding', []);
    }

    public function all(): array
    {
        $settings = $this->cache->all()['branding'];
        $effective = $this->effectiveValues();

        return collect($this->defaults())->mapWithKeys(function ($default, string $key) use ($settings) {
            $override = $settings->get($key);

            return [$key => [
                'key' => $key,
                'value' => $override instanceof BrandingSetting ? $override->value : $default,
                'default' => $default,
                'has_override' => $override instanceof BrandingSetting,
            ]];
        })->map(function (array $item, string $key) use ($effective) {
            $item['effective_value'] = $effective[$key] ?? $item['value'];
            $item['source'] = $item['has_override'] ? 'database' : $this->fallbackSource($key);

            return $item;
        })->all();
    }

    public function value(string $key, ?string $fallback = null): ?string
    {
        return $this->effectiveValues()[$key] ?? $fallback;
    }

    public function effectiveValues(): array
    {
        $settings = $this->cache->all()['branding'];

        return collect($this->defaults())->mapWithKeys(function ($default, string $key) use ($settings) {
            $override = $settings->get($key);

            if ($override instanceof BrandingSetting && $override->value !== null && $override->value !== '') {
                return [$key => $override->value];
            }

            return [$key => $this->fallbackValue($key, $default)];
        })->all();
    }

    public function clientCompany(): object
    {
        $config = config('client_company', []);
        $effective = $this->effectiveValues();

        return (object) array_merge($config, [
            'name' => $effective['company_name'] ?? $effective['client_name'] ?? ($config['name'] ?? 'Default Company'),
            'owner_name' => $config['owner_name'] ?? 'Owner',
            'logo' => $config['logo'] ?? 'default_logo.png',
            'logo_text' => $effective['logo_text'] ?? ($config['logo_text'] ?? null),
            'logo_svg_path' => $config['logo_svg_path'] ?? 'images/default.svg',
            'phone_number' => $effective['phone'] ?? ($config['phone_number'] ?? ''),
            'pusher_enabled' => $config['pusher_enabled'] ?? false,
        ]);
    }

    public function save(string $key, string $value): BrandingSetting
    {
        if (!array_key_exists($key, $this->defaults())) {
            throw new \InvalidArgumentException('Unknown branding key.');
        }

        $this->assertSafeValue($key, $value);

        $setting = BrandingSetting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $value,
                'type' => str_contains($key, 'color') ? 'color' : 'string',
                'updated_by' => Auth::id(),
                'created_by' => Auth::id(),
            ],
        );

        $this->refreshSharedValues();
        $this->auditLogs->record('settings.branding_saved', [
            'branding_key' => $key,
            'value' => $value,
        ], ['module' => 'developer_settings']);

        return $setting;
    }

    public function reset(string $key): void
    {
        if (!array_key_exists($key, $this->defaults())) {
            throw new \InvalidArgumentException('Unknown branding key.');
        }

        BrandingSetting::where('key', $key)->delete();
        $this->refreshSharedValues();
        $this->auditLogs->record('settings.branding_reset', [
            'branding_key' => $key,
        ], ['module' => 'developer_settings']);
    }

    protected function assertSafeValue(string $key, string $value): void
    {
        if (str_contains($key, 'color')) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
                throw new \InvalidArgumentException('Theme color must be a hex color.');
            }

            return;
        }

        if (mb_strlen($value) > 120 || $value !== strip_tags($value) || preg_match('/[<>]/', $value)) {
            throw new \InvalidArgumentException('Branding text must be plain text up to 120 characters.');
        }

        $this->valueGuard->assertNoSecretLikeValue($value);
    }

    protected function fallbackValue(string $key, mixed $default): ?string
    {
        return match ($key) {
            'app_name' => $this->firstFilled(
                config('client_company.logo_text'),
                config('branding.app_name'),
                'GarmentsOS PRO',
            ),
            'company_name',
            'client_name' => $this->firstFilled(
                config('client_company.name'),
                config('branding.company_name'),
                config('branding.client_name'),
                'Default Company',
            ),
            'logo_text' => $this->firstFilled(
                config('client_company.logo_text'),
                config('branding.logo_text'),
                config('branding.app_name'),
                null,
            ),
            'phone' => $this->firstFilled(
                config('client_company.phone_number'),
                config('branding.phone'),
                '',
            ),
            'theme_primary_color' => $this->validColor(config('branding.theme_primary_color')) ?: '#2563eb',
            'theme_secondary_color' => $this->validColor(config('branding.theme_secondary_color')) ?: '#1f2937',
            'theme_accent_color' => $this->validColor(config('branding.theme_accent_color')) ?: '#2563eb',
            'print_footer_text' => $this->firstFilled(config('branding.print_footer_text'), ''),
            default => is_scalar($default) ? (string) $default : null,
        };
    }

    protected function fallbackSource(string $key): string
    {
        return match ($key) {
            'app_name', 'company_name', 'client_name', 'logo_text', 'phone' => 'client_company_config',
            default => 'branding_config',
        };
    }

    protected function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if ($value !== null && $value !== '') {
                return (string) $value;
            }
        }

        return null;
    }

    protected function validColor(mixed $value): ?string
    {
        $value = is_string($value) ? $value : '';

        return preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? $value : null;
    }

    protected function refreshSharedValues(): void
    {
        $this->cache->forget();
        app()->forgetInstance('branding.effective_values');
        app()->forgetInstance('client_company');
        View::share('client_company', $this->clientCompany());
        View::share('branding', app('branding.effective_values'));
    }
}
