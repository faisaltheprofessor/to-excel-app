<?php

use App\Livewire\CreateImporter;
use App\Livewire\Importer;
use App\Livewire\TreeEditor;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return redirect("/importer");
})->name('home');


Route::get('/importer/new', function () {
    $draft = \App\Models\OrganizationStructure::create([
        'title' => 'Unbenannter Struktur',
        'data'  => [],
    ]);
    return redirect()->route('importer.edit', $draft->id);
})->name('importer.new');

Route::get('/importer/{tree}', TreeEditor::class)->name('importer.edit');


Route::get("/importer", \App\Livewire\TreeIndex::class)->name('importer.index');
Route::get("/importer/create", Importer::class)->name('importer.create');
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
