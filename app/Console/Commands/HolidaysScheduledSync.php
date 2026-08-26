<?php

namespace App\Console\Commands;

use App\Services\HolidaysSyncService;
use App\Services\HolidaysSyncTrackerService;
use Illuminate\Console\Command;
use Throwable;

class HolidaysScheduledSync extends Command
{
    protected $signature = 'holidays:scheduled-sync';
    protected $description = 'Synchronise le calendrier scolaire et exporte si changements';

    public function handle(
        HolidaysSyncService $service,
        HolidaysSyncTrackerService $tracker
    ): int {
        $zones = config('calendars.zones');
        $results = [];

        foreach ($zones as $zone => $url) {
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
                logger()->error("holidays:sync {$zone} failed", ['error' => $e->getMessage()]);
            }
        }

        // Vérifier s'il y a des changements
        if ($tracker->recordChanges($results)) {
            $this->info('Changements détectés, lancement de l\'export...');

            $exportCode = $this->call('holidays:export');

            if ($exportCode === self::SUCCESS) {
                $this->info('Export réussi.');
                $tracker->clearChanges();
            } else {
                $this->error('L\'export a échoué.');
                return self::FAILURE;
            }
        } else {
            $this->info('Aucun changement détecté, export ignoré.');
        }

        return self::SUCCESS;
    }
}
