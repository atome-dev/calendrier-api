<?php

namespace App\Console\Commands;

use App\Services\HolidaysJsonExportService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('holidays:export {path? : Chemin du fichier de sortie (par défaut config(calendars.export.path))}')]
#[Description('Génère le fichier JSON des vacances scolaires à partir de la base de données')]
class HolidaysExportJson extends Command
{
    public function handle(HolidaysJsonExportService $service): int
    {
        $disk = Storage::disk(config('calendars.export.disk'));
        $path = $this->argument('path') ?? config('calendars.export.path');

        $data = $service->export();

        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            $this->error('Échec de l\'encodage JSON : ' . json_last_error_msg());
            return self::FAILURE;
        }

        $disk->put($path, $json);

        $this->info(sprintf(
            'Export terminé : %d périodes écrites dans %s',
            count($data['periodes']),
            $path
        ));

        return self::SUCCESS;
    }
}
