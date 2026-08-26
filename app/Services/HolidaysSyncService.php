<?php

namespace App\Services;

use App\Models\Holiday;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HolidaysSyncService
{
    /**
     * Résumés (ou motifs) à exclure de l'export : jours fériés,
     * commémorations, événements non liés aux vacances scolaires.
     */
    private const EXCLUDED_SUMMARIES = [
        "Abolition de l'esclavage",
        "Abolition de l’esclavage", // variante apostrophe typographique
    ];


    public function syncZone(string $zone, string $url): array
    {
        $ics = Http::get($url)->throw()->body();
        $events = $this->parseIcs($ics);

        $seenUids = [];
        $created = $updated = 0;

        foreach ($events as $event) {

            if (empty($event['starts_at']) || empty($event['ends_at'])) {
                Log::warning('Événement ICS ignoré : dates manquantes', ['uid' => $event['uid'] ?? 'inconnu']);
                continue;
            }

            $hash = hash('sha256', $event['uid'] . $event['summary'] . $event['description'] . $event['starts_at'] . $event['ends_at']);
            $seenUids[] = $event['uid'];

            $existing = Holiday::withTrashed()->where('uid', $event['uid'])->first();

            if (!$existing) {
                Holiday::create([
                    'uid' => $event['uid'],
                    'zone' => $zone,
                    'summary' => $event['summary'],
                    'description' => $event['description'],
                    'starts_at' => $event['starts_at'],
                    'ends_at' => $event['ends_at'],
                    'population' => $event['population'] ?? null,
                    'raw_ics_hash' => $hash,
                ]);
                $created++;
                continue;
            }

            if ($existing->trashed()) {
                $existing->restore();
            }

            //dump("###### NEW EVENT #####");
            //dump($event);
            //dump("###### DB EVENT #####");
            //dump($existing);
            //dump($existing->raw_ics_hash . '/' . $hash);


            if ($existing->raw_ics_hash !== $hash) {

                //dump($existing->raw_ics_hash);


                $existing->update([
                    'summary' => $event['summary'],
                    'description' => $event['description'],
                    'starts_at' => $event['starts_at'],
                    'ends_at' => $event['ends_at'],
                    'population' => $event['population'] ?? null,
                    'raw_ics_hash' => $hash,
                ]);
                $updated++;
            }
        }

        $deleted = Holiday::zone($zone)
            ->whereNotIn('uid', $seenUids)
            ->get()
            ->each(fn ($h) => $h->delete())
            ->count();

        return compact('created', 'updated', 'deleted');
    }

    private function parseIcs(string $content): array
    {
        $events = [];
        preg_match_all('/BEGIN:VEVENT(.*?)END:VEVENT/s', $content, $matches);

        foreach ($matches[1] as $block) {
            $uid = $this->extract($block, 'UID');
            $summary = $this->extract($block, 'SUMMARY');
            $dtstart = $this->extract($block, 'DTSTART');
            $dtend = $this->extract($block, 'DTEND');
            $descriptionRaw = $this->extract($block, 'DESCRIPTION');

            $population = null;
            if ($descriptionRaw && preg_match('/population:\s*([^\\\\n]*)/', $descriptionRaw, $m)) {
                $population = trim($m[1]) === '-' ? null : trim($m[1]);
            }

            $events[] = [
                'uid' => $uid,
                'summary' => $summary,
                'description' => $descriptionRaw,
                'starts_at' => $this->parseDate($dtstart),
                'ends_at' => $this->parseDate($dtend),
                'population' => $population,
            ];
        }

        return $events;
    }

    private function extract(string $block, string $key): ?string
    {
        // Matche "KEY:" ou "KEY;PARAM=...:" en début de ligne
        if (preg_match('/^' . $key . '(?:;[^:]*)?:(.*)$/m', $block, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    private function parseDate(?string $raw): ?string
    {
        if (!$raw) {
            return null;
        }

        // Format datetime UTC : 20260820T093335Z
        if ($date = \DateTime::createFromFormat('Ymd\THis\Z', $raw)) {
            return $date->format('Y-m-d H:i:s');
        }

        // Format datetime local sans Z : 20260820T093335
        if ($date = \DateTime::createFromFormat('Ymd\THis', $raw)) {
            return $date->format('Y-m-d H:i:s');
        }

        // Format date seule (VALUE=DATE) : 20260820
        if ($date = \DateTime::createFromFormat('Ymd', $raw)) {
            return $date->format('Y-m-d 00:00:00');
        }

        Log::warning('Format de date ICS non reconnu', ['raw' => $raw]);
        return null;
    }
}
