<?php

namespace App\Console\Commands;

use App\Services\HolidaysSyncService;
use App\Services\HolidaysSyncTrackerService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('holidays:sync {zone?}')]
#[Description('Synchronise le calendrier scolaire (une zone ou toutes)')]
class HolidaysSync extends Command
{
    public function handle(
        HolidaysSyncService $service,
        HolidaysSyncTrackerService $tracker
    ): int {
        $zones = config('calendars.zones');
        $target = $this->argument('zone');

        if ($target && !isset($zones[$target])) {
            $this->error("Zone inconnue : {$target}");
            return self::FAILURE;
        }

        $toSync = $target ? [$target => $zones[$target]] : $zones;
        $results = [];

        foreach ($toSync as $zone => $url) {
            try {
                $result = $service->syncZone($zone, $url);
                $results[$zone] = $result;

                $this->info(
                    "{$zone} : {$result['created']} créés, "
                    . "{$result['updated']} modifiés, "
                    . "{$result['deleted']} supprimés"
                );

                logger()->info("holidays:sync {$zone}", $result);
            } catch (Throwable $e) {
                $this->error("Échec pour {$zone} : {$e->getMessage()}");
                logger()->error("holidays:sync {$zone} failed", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Tracker les modifications
        $tracker->recordChanges($results);

        return self::SUCCESS;
    }
}
