<?php

use App\Services\Settings\LabelSettingsService;
use App\Services\Settings\ModuleAvailabilityService;

if (!function_exists('label_text')) {
    function label_text(string $key, ?string $fallback = null): string
    {
        try {
            return app(LabelSettingsService::class)->text($key, $fallback);
        } catch (Throwable) {
            return $fallback ?? (string) config('labels.' . $key, $key);
        }
    }
}

if (!function_exists('module_enabled')) {
    function module_enabled(string $key): bool
    {
        try {
            return app(ModuleAvailabilityService::class)->isEffectivelyVisibleInSidebar($key);
        } catch (Throwable) {
            return true;
        }
    }
}

if (!function_exists('format_release_notes')) {
    function format_release_notes(mixed $notes): array
    {
        if (is_array($notes)) {
            $items = $notes;
        } else {
            $text = trim((string) $notes);
            if ($text === '') {
                return [];
            }

            $decoded = json_decode($text, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $items = $decoded;
            } else {
                $normalized = preg_replace('/\r\n?/', "\n", $text);
                $normalized = preg_replace('/(?:^|\n)\s*[-*•]\s+/u', "\n", $normalized);
                $normalized = preg_replace('/\s*(?:;|\|)\s*/', "\n", $normalized);
                $normalized = preg_replace('/(?<=[.!?])\s+(?=[A-Z0-9])/', "\n", $normalized);
                $items = preg_split('/\n+/', $normalized) ?: [];
            }
        }

        return collect($items)
            ->flatMap(function ($item) {
                if (is_array($item)) {
                    return collect($item)->flatten()->all();
                }

                return [$item];
            })
            ->map(fn ($item) => trim(preg_replace('/^\s*[-*•]\s*/u', '', (string) $item)))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
