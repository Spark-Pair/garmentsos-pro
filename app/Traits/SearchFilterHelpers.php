<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

trait SearchFilterHelpers
{
    private function searchFilterTokens($value): Collection
    {
        return collect(preg_split('/[,\r\n]+/', (string) $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->unique(fn ($item) => mb_strtolower($item))
            ->values();
    }

    private function searchFilterRangeBounds(string $value): ?array
    {
        if (!preg_match('/^(\d+)\s*(?:-|to|se)\s*(\d+)$/i', trim($value), $matches)) {
            return null;
        }

        $start = (int) $matches[1];
        $end = (int) $matches[2];
        $width = max(strlen($matches[1]), strlen($matches[2]));

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        return [$start, $end, $width];
    }

    private function searchFilterMatchesToken(string $haystack, string $token): bool
    {
        $bounds = $this->searchFilterRangeBounds($token);
        if (!$bounds) {
            return str_contains(mb_strtolower($haystack), mb_strtolower($token));
        }

        [$start, $end, $width] = $bounds;
        preg_match_all('/\d+/', $haystack, $matches);
        $lastNumber = collect($matches[0] ?? [])->last();

        if ($lastNumber === null || strlen($lastNumber) !== $width) {
            return false;
        }

        $number = (int) $lastNumber;
        return $number >= $start && $number <= $end;
    }

    private function searchFilterMatchingIds(string $modelClass, string $textColumn, $value): Collection
    {
        $tokens = $this->searchFilterTokens($value);
        if ($tokens->isEmpty()) {
            return collect();
        }

        return $modelClass::query()
            ->get(['id', $textColumn])
            ->filter(function ($model) use ($tokens, $textColumn) {
                $text = (string) ($model->{$textColumn} ?? '');

                return $tokens->contains(fn ($token) => $this->searchFilterMatchesToken($text, (string) $token));
            })
            ->pluck('id')
            ->values();
    }

    private function searchFilterWhereLikeAny(Builder $query, string $column, $value): Builder
    {
        $tokens = $this->searchFilterTokens($value);
        if ($tokens->isEmpty()) {
            return $query;
        }

        return $query->where(function (Builder $scope) use ($tokens, $column) {
            foreach ($tokens as $token) {
                $scope->orWhere($column, 'like', "%{$token}%");
            }
        });
    }
}
