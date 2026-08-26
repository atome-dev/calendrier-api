<?php

use App\Http\Controllers\CalendarController;
use Illuminate\Support\Facades\Route;


Route::get('/vacances', [CalendarController::class, 'index'])
    ->name('api.calendars.index');

// Catch-all pour les autres routes
Route::fallback(function () {
    return response()->json([
        'message' => 'Ressource non trouvée.',
    ], 404);
});
