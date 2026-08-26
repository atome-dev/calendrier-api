<?php

use Illuminate\Support\Facades\Route;

// Catch-all pour les autres routes
Route::fallback(function () {
    return response()->json([
        'message' => 'Ressource non trouvée.',
    ], 404);
});
