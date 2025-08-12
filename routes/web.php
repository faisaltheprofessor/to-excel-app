<?php

use App\Livewire\Importer;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect("/importer");
})->name('home');

Route::get("/importer", Importer::class)->name('importer');
Route::get('/download-excel/{filename}', function ($filename) {
    $path = 'temp/' . $filename;
    if (!Storage::exists($path)) {
        abort(404);
    }
    return response()->download(storage_path('app/private/' . $path))->deleteFileAfterSend(true);
})->name('download-excel');


Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
