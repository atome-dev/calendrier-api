<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class HolidaysJsonExportService
{
    public const ZONES = [
        'A', 'B', 'C', 'Corse', 'Guadeloupe', 'Martinique', 'Guyane', 'Reunion',
        'Mayotte', 'NouvelleCaledonie', 'Polynesie', 'WallisEtFutuna', 'SaintPierreEtMiquelon',
    ];

    /**
     * Correspondance nom de zone BDD -> nom de zone JSON.
     * Clé = valeur exacte stockée dans holidays.zone
     */
    private const ZONE_MAP = [
        'Zone A' => 'A',
        'Zone B' => 'B',
        'Zone C' => 'C',
        'Corse' => 'Corse',
        'Guadeloupe' => 'Guadeloupe',
        'Martinique' => 'Martinique',
        'Guyanne' => 'Guyane',
        'Reunion' => 'Reunion',
        'Mayotte' => 'Mayotte',
        'NouvelleCaledonie' => 'NouvelleCaledonie',
        'Polynesie' => 'Polynesie',
        'WallisEtFutuna' => 'WallisEtFutuna',
        'SaintPierreEtMiquelon' => 'SaintPierreEtMiquelon',
    ];

    /**
     * Résumés (ou motifs) à exclure de l'export : jours fériés,
     * commémorations, événements non liés aux vacances scolaires.
     */
    private const EXCLUDED_SUMMARIES = [
        "Abolition de l'esclavage",
        "Abolition de l’esclavage", // variante apostrophe typographique
        "Abolition de l?esclavage", // variante apostrophe typographique
    ];

    private function isExcluded(string $summary): bool
    {
        foreach (self::EXCLUDED_SUMMARIES as $excluded) {
            if (str_contains($summary, $excluded)) {
                return true;
            }
        }

        return false;
    }

    public function export(): array
    {
        $periods = [];
        $boundaries = [];

        Holiday::query()->get()->each(function (Holiday $h) use (&$periods, &$boundaries): void {

            if ($this->isExcluded($h->summary)) {
                return;
            }

            $zone = $this->mapZone($h->zone);
            $kind = $this->boundaryKind($h->summary);

            if ($kind !== null) {
                // C'est un événement "Début" ou "Fin" isolé : à apparier
                $boundaries[$zone . "\0" . $this->periodName($h->summary)][$kind] = $h;
                return;
            }

            // Cas "période complète" : starts_at et ends_at sur le même événement
            $startsAt = $h->starts_at;
            $endsAt = $this->normalizeEndDate($h->starts_at, $h->ends_at);

            // Sinon, période complète autonome
            $periods[] = [
                'nom' => $this->periodName($h->summary),
                'zone' => $zone,
                'debut' => $h->starts_at->format('Y-m-d'),
                'fin' => $this->fin($startsAt, $endsAt),
            ];
        });

        foreach ($boundaries as $key => $pair) {
            if (!isset($pair['start'], $pair['end'])) {
                continue;
            }
            [$zone] = explode("\0", $key, 2);

            $start = $pair['start']->starts_at;
            // La vraie date de fin de la période vient de l'événement "end"
            $end = $pair['end']->ends_at ?? $pair['end']->starts_at;
            $end = $this->normalizeEndDate($start, $end);

            $periods[] = [
                'nom' => $this->periodName($pair['start']->summary),
                'zone' => $zone,
                'debut' => $pair['start']->starts_at->format('Y-m-d'),
                'fin' => $this->fin($start, $end),
            ];
        }

        usort($periods, static fn(array $a, array $b): int => [$a['debut'], $a['fin']] <=> [$b['debut'], $b['fin']]);

        return ['zones' => self::ZONES, 'periodes' => $periods];
    }

    public function exportToFile(string $path): void
    {
        $json = json_encode(
                $this->export(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ) . PHP_EOL;

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $path) === 1) {
            file_put_contents($path, $json);
        } else {
            Storage::disk('local')->put($path, $json);
        }
    }

    private function mapZone(string $zone): string
    {
        return self::ZONE_MAP[$zone] ?? $zone;
    }

    private function periodName(string $summary): string
    {
        return preg_replace('/^(?:Début|Fin)\s+(?:des|de)\s+/u', '', $summary) ?? $summary;
    }

    private function boundaryKind(string $summary): ?string
    {
        if (preg_match('/^Début\s+(?:des|de)\s+/u', $summary) === 1) return 'start';
        if (preg_match('/^Fin\s+(?:des|de)\s+/u', $summary) === 1) return 'end';
        return null;
    }

    private function fin(\Carbon\CarbonInterface $startsAt, \Carbon\CarbonInterface $endsAt): string
    {
        if ($startsAt->isSameDay($endsAt)) {
            return $endsAt->format('Y-m-d');
        }

        return $endsAt->subDay()->format('Y-m-d');
    }

    private function normalizeEndDate(\Carbon\CarbonInterface $startsAt, \Carbon\CarbonInterface $endsAt): \Carbon\CarbonInterface
    {
        // Cas d'erreur connue : la fin est antérieure au début (année oubliée dans la source)
        if ($endsAt->lessThan($startsAt)) {
            $corrected = $endsAt->addYear();

            Log::warning('Correction automatique d\'une date de fin incohérente dans le flux ICS', [
                'starts_at' => $startsAt->toDateString(),
                'ends_at_original' => $endsAt->toDateString(),
                'ends_at_corrected' => $corrected->toDateString(),
            ]);

            return $corrected;
        }

        return $endsAt;
    }
}
