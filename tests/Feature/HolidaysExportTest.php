<?php

use App\Models\Holiday;
use App\Services\HolidaysJsonExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function holiday(array $overrides = []): Holiday
{
    return Holiday::create(array_merge([
        'uid'          => 'direct',
        'zone'         => 'A',
        'summary'      => 'Vacances de la Toussaint',
        'description'  => 'Vacances de la Toussaint - A',
        'starts_at'    => '2026-10-17',
        'ends_at'      => '2026-11-02',
        'population'   => null,
        'raw_ics_hash' => hash('sha256', 'direct'),
    ], $overrides));
}

it('exports a direct period', function (): void {
    holiday(['uid' => 'direct', 'zone' => 'A', 'summary' => "Vacances de la Toussaint", 'starts_at' => '2026-10-17', 'ends_at' => '2026-11-01']);

    expect((new HolidaysJsonExportService)->export()['periodes'][0])
        ->toMatchArray(['nom' => "Vacances de la Toussaint", 'zone' => 'A', 'debut' => '2026-10-17', 'fin' => '2026-10-31']);
});

it('pairs separate boundaries', function (): void {
    holiday(['uid' => 'start', 'zone' => 'B', 'summary' => "Début des Vacances d'Été", 'starts_at' => '2026-07-04']);
    holiday(['uid' => 'end', 'zone' => 'B', 'summary' => "Fin des Vacances d'Été", 'ends_at' => '2026-09-01']);

    //$periodes = (new HolidaysJsonExportService)->export()['periodes'];
    //dump($periodes);

    expect((new HolidaysJsonExportService)->export()['periodes'])
        ->toContain(['nom' => "Vacances d'Été", 'zone' => 'B', 'debut' => '2026-07-04', 'fin' => '2026-08-31']);
});

it('returns expected keys zones formatted and sorted periods', function (): void {
    holiday(['uid' => 'later', 'zone' => 'A', 'summary' => "Vacances de Noël", 'starts_at' => '2026-12-19', 'ends_at' => '2027-01-03']);
    holiday(['uid' => 'early', 'zone' => 'C', 'summary' => "Vacances de la Toussaint", 'starts_at' => '2026-10-17', 'ends_at' => '2026-10-31']);
    holiday(['uid' => 'middle', 'zone' => 'B', 'summary' => "Vacances d'Hiver", 'starts_at' => '2027-02-06', 'ends_at' => '2027-02-21']);

    $e = (new HolidaysJsonExportService)->export();

    expect(array_keys($e))->toBe(['zones', 'periodes'])
        ->and($e['zones'])->toBe(HolidaysJsonExportService::ZONES)
        ->and(array_column($e['periodes'], 'debut'))->toBe(['2026-10-17', '2026-12-19', '2027-02-06']);

    foreach ($e['periodes'] as $p) {
        expect($p['debut'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
            ->and($p['fin'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
    }
});

it('maps BDD zone names to JSON zone names', function (): void {
holiday(['uid' => '1', 'zone' => 'Zone A', 'summary' => 'Vacances de Noël', 'starts_at' => '2026-12-19', 'ends_at' => '2027-01-04']);
holiday(['uid' => '2', 'zone' => 'Guyanne', 'summary' => 'Vacances de Noël', 'starts_at' => '2026-12-19', 'ends_at' => '2027-01-04']);
holiday(['uid' => '3', 'zone' => 'WallisEtFutuna', 'summary' => 'Vacances de Noël', 'starts_at' => '2026-12-19', 'ends_at' => '2027-01-04']);

$zones = array_column((new HolidaysJsonExportService)->export()['periodes'], 'zone');

expect($zones)->toContain('A')
->and($zones)->toContain('Guyane')
->and($zones)->toContain('WallisEtFutuna')
->and($zones)->not->toContain('Zone A')
->and($zones)->not->toContain('Guyanne');
});

it('checks only one day event', function (): void {
    holiday([
        'uid' => 'ascension',
        'zone' => 'A',
        'summary' => "Pont de l'Ascension",
        'starts_at' => '2027-05-07 00:00:00',
        'ends_at' => '2027-05-07 00:00:00',
    ]);

    $periods = (new HolidaysJsonExportService)->export()['periodes'];

    expect($periods)->toContain([
        'nom' => "Pont de l'Ascension",
        'zone' => 'A',
        'debut' => '2027-05-07',
        'fin' => '2027-05-07', // pas de J-1 car événement d'un seul jour
    ]);
});

it('applies previous end date', function (): void {
    holiday([
        'uid' => 'toussaint',
        'zone' => 'A',
        'summary' => 'Vacances de la Toussaint',
        'starts_at' => '2026-10-17 00:00:00',
        'ends_at' => '2026-11-02 00:00:00',
    ]);

    $periods = (new HolidaysJsonExportService)->export()['periodes'];

    expect($periods)->toContain([
        'nom' => 'Vacances de la Toussaint',
        'zone' => 'A',
        'debut' => '2026-10-17',
        'fin' => '2026-11-01',
    ]);
});
