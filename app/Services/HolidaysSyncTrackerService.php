<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class HolidaysSyncTrackerService
{
    private const CACHE_KEY = 'holidays_sync_changes';
    private const CACHE_TTL = 604800; // 7 jours

    public function recordChanges(array $results): bool
    {
        $hasChanges = collect($results)->some(function (array $zoneResult) {
            return $zoneResult['created'] > 0
                || $zoneResult['updated'] > 0
                || $zoneResult['deleted'] > 0;
        });

        if ($hasChanges) {
            Cache::put(self::CACHE_KEY, [
                'timestamp' => now(),
                'results' => $results,
            ], self::CACHE_TTL);
        }

        return $hasChanges;
    }

    public function hasChanges(): bool
    {
        return Cache::has(self::CACHE_KEY);
    }

    public function getChanges(): array
    {
        return Cache::pull(self::CACHE_KEY, []);
    }

    public function clearChanges(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
