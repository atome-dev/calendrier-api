<?php
use App\Models\Holiday;
use App\Services\HolidaysSyncService;
use Illuminate\Support\Facades\Http;

it('creates initial events', function () {

    $original = file_get_contents(base_path('tests/Fixtures/Guadeloupe.ics'));

    Http::fake(['*' => Http::response($original)]);

    $result = app(HolidaysSyncService::class)->syncZone('Guadeloupe', 'https://fake.test/cal.ics');

    expect($result['created'])->toBe(95)
        ->and(Holiday::count())->toBe(95);
});

it('updates event', function () {
    $original = file_get_contents(base_path('tests/Fixtures/Guadeloupe.ics'));
    $modified = str_replace("Début des Vacances d'Été", "Début des Vacances d'Été (modifié)", $original);

    Http::fakeSequence()
        ->push($original)
        ->push($modified);

    app(HolidaysSyncService::class)->syncZone('Guadeloupe', 'https://fake.test/cal.ics');
    $result = app(HolidaysSyncService::class)->syncZone('Guadeloupe', 'https://fake.test/cal.ics');

    expect($result['updated'])->toBe(1)
        ->and(Holiday::where('summary', "Début des Vacances d'Été (modifié)")->exists())->toBeTrue();
});


it('deletes an event', function () {

    $original = file_get_contents(base_path('tests/Fixtures/Guadeloupe.ics'));

    $key = preg_quote('Vacances de Carnaval', '/');
    $pattern = "/BEGIN:VEVENT(?:(?!END:VEVENT).)*?{$key}(?:(?!END:VEVENT).)*?END:VEVENT\r?\n?/s";
    $reduced = preg_replace($pattern, '', $original);

    Http::fakeSequence()
        ->push($original)
        ->push($reduced);

    app(HolidaysSyncService::class)->syncZone('Guadeloupe', 'https://fake.test/cal.ics');
    $result = app(HolidaysSyncService::class)->syncZone('Guadeloupe', 'https://fake.test/cal.ics');

    expect($result['deleted'])->toBe(8)
        ->and(Holiday::withTrashed()->where('summary', $key)->count())->toBe(8);
});
